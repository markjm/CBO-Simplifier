"""
Scrapes the given CBO page, providing all the dollar figures found in the page
and the context around them.
"""
import datetime
import dateutil.parser
import re
import sys
from urllib.request import urlopen

from bs4 import BeautifulSoup

COST_CONTEXT = re.compile(r'(\w+\W){0,10}(\$[0-9,]+(\.[0-9]+)?)\W(billion|million|thousand)+\Wover\Wthe\W[0-9]+-[0-9]+\Wperiod')
LONG_DATE = re.compile('(January|February|March|April|May|June|July|August|September|October|November|December) [0-9]{1,2}, [0-9]{4}')
COMMITTEE_NAME = re.compile('by the (.+) on (January|February|March|April|May|June|July|August|September|October|November|December)')

def split_sentences(text):
    """
    Splits a paragraph into words, splitting on sentence and word delimiters.
    """
    sentences = re.split('[.!?]+', text)
    return sentences

def compute_congress(bill_date):
    """
    Figures out what Congress applies to the given date.
    """
    # Note that each Congress seats on January 3rd, so consider an early
    # January date as the previous Congress
    if bill_date.month == 1 and bill_date.day < 3:
        bill_date = datetime.date(bill_date.year - 1, 12, 31)

    return (bill_date.year - 1789) // 2 + 1

class BillInfo:
    """
    A single CBO page on a particular bill.
    """
    def __init__(self, url):
        self.url = url
        self.name = None
        self.cost_suggestions = []
        self.pub_date = None
        self.request_date = None
        self.committee = None
        self.congress = None

        with urlopen(url) as page:
            self.soup = BeautifulSoup(page.read(), 'html.parser')

        self._process()

    def _process(self):
        self.name = self.soup.h1.get_text()

        self.cost_suggestions = self._get_dollar_contexts()
        pub_date_raw = self.soup.find(
            'span',
            {'class': 'date-display-single', 'property': 'dc:date'}
        )['content']

        self.pub_date = dateutil.parser.parse(pub_date_raw).date()

        request_committee_top = self.soup.find('div', {'class': 'summary'})
        request_committee_text = request_committee_top.get_text()

        self.committee = COMMITTEE_NAME.search(request_committee_text).group(1)
        request_date_raw = LONG_DATE.search(request_committee_text).group()
        self.request_date = dateutil.parser.parse(request_date_raw).date()

        self.congress = compute_congress(self.request_date)

    def _get_dollar_contexts(self):
        """
        Returns a list of "word contexts" that surround likely cost contexts.
        """
        contexts = []
        article_pars = self.soup.article.find_all('p')

        for paragraph in article_pars:
            paragraph_text = paragraph.get_text()
            for match in COST_CONTEXT.finditer(paragraph_text):
                contexts.append(match.group())

        return contexts

if len(sys.argv) == 1:
    print(sys.argv[0], '<url>')
    sys.exit(1)

info = BillInfo(sys.argv[1])
print('#', info.name)

print('## Requested')
print('On {} by the {} of the {} Congress'.format(
            info.request_date,
            info.committee, 
            info.congress))

print('## Published')
print(info.pub_date)

print('## Cost Suggestions')
for sentence in info.cost_suggestions:
    print(' -', sentence)
