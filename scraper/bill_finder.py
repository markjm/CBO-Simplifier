import os
import re
import random
import time

import feedparser
import pymysql, pymysql.cursors

import scrape

CBO_RSS = 'https://www.cbo.gov/publications/all/rss.xml'
COST_ESTIMATE_DESCRIPTION = re.compile(
    '^As (passed|(ordered)? reported)'
)

MYSQL_HOST = os.environ['MYSQL_HOST']
MYSQL_USER = os.environ['MYSQL_USER']
MYSQL_PASSWORD = os.environ['MYSQL_PASSWORD']
MYSQL_DATABASE = os.environ['MYSQL_DATABASE']

def process_current_feed():
    """
    Pulls the current version of the CBO RSS, and gets the current Bills from
    the RSS feed.
    """
    feed = feedparser.parse(CBO_RSS)

    bills = []
    for entry in feed.entries:
        if not COST_ESTIMATE_DESCRIPTION.match(entry.description):
            continue

        url = entry.link
        bills.append(scrape.Bill(url))

        # Sleep, to avoid sending a bunch of traffic at once
        time.sleep(random.random() * 5)

    print('>>> Collected', len(bills), 'bills from the CBO')
    return bills

def insert_bills_into_database(bills):
    """
    Inserts a series of Bills objects into the database, under the table 
    PendingBills
    """
    conn = pymysql.connect(
            host=MYSQL_HOST, 
            user=MYSQL_USER, 
            password=MYSQL_PASSWORD, 
            db=MYSQL_DATABASE, 
            charset='utf8',
            cursorclass=pymysql.cursors.DictCursor)

    try:
        with conn.cursor() as cursor:
            print('>>> Adding pending bills')
            for bill in bills:
                if 'Committee' not in bill.committee:
                    # This indicates that the committee is something fake, 
                    # like "U.S. House of Representatives" - more often than
                    # not, these are actually bills that are passed and not
                    # ones that are under review, so we can ignore these
                    continue

                if bill.pub_date is not None:
                    published = bill.pub_date.strftime('%Y-%m-%d')
                else:
                    published = None

                cursor.execute('''
                    INSERT IGNORE INTO PendingBills(title, summary, committee, published, code, cbo_url, pdf_url)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                ''', (bill.name, bill.summary, bill.committee, published, bill.code, bill.url, bill.pdf))

            # Now, we also have to go back and remove the rows which already
            # have corresponding URLs in the main table
            print('>>> Finding bills in the RSS that we have already seen')
            cursor.execute('''
                SELECT PendingBills.id AS to_delete FROM 
                PendingBills INNER JOIN Bills 
                ON PendingBills.cbo_url = Bills.cbo_url
            ''')

            print('>>> Deleting duplicate pending bills')
            to_delete = [(row['to_delete'],) for row in cursor]
            cursor.executemany('DELETE FROM PendingBills WHERE id = %s', to_delete)

        conn.commit()
    finally:
        conn.close()

if __name__ == '__main__':
    insert_bills_into_database(process_current_feed())
