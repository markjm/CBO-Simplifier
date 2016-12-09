"""
Scrapes the given CBO page, providing all the dollar figures found in the page
and the context around them.
"""
import datetime
import re
from urllib.request import urlopen

from bs4 import BeautifulSoup
import dateutil.parser

COST_CONTEXT = re.compile(r'(\w+\W){0,10}(\$[0-9,]+(\.[0-9]+)?)\W(billion|million|thousand)+\Wover\Wthe\W[0-9]+-[0-9]+\Wperiod')
LONG_DATE = re.compile('(January|February|March|April|May|June|July|August|September|October|November|December) [0-9]{1,2}, [0-9]{4}')
COMMITTEE_NAME = re.compile('by the (.+) on (January|February|March|April|May|June|July|August|September|October|November|December)')
BILL_CODE = re.compile(r'(S\.|H\.R\.) [0-9]+')

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

class Bill:
    """
    A single CBO page on a particular bill.
    """
    def __init__(self, url):
        self.url = url
        self.pdf = None
        self.name = None
        self.code = None
        self.summary = None
        self.cost_suggestions = []
        self.pub_date = None
        self.request_date = None
        self.committee = None
        self.congress = None
        self.cost_suggestions = None

        with urlopen(url) as page:
            self.soup = BeautifulSoup(page.read(), 'html.parser')

        self._process()

    def _process(self):
        # Note that this should not fail under normal circumstances - every
        # page I've seen, even older ones, always have a PDF analysis linked
        # on the page
        self.pdf = self.soup.find('a', {'class': 'read-complete-document'})['href']

        try:
            code_name = self.soup.h1.get_text()
            self.code = BILL_CODE.search(code_name).group()
            self.name = code_name[len(self.code):].strip(', ')
        except AttributeError: # get_text() not defined on None
            self.name = None
            self.code = None

        try:
            summary_par = self.soup.article.find('p')
            self.summary = summary_par.get_text()
        except AttributeError:
            self.summary = None

        self.cost_suggestions = self._get_dollar_contexts()

        try:
            pub_date_raw = self.soup.find(
                'span',
                {'class': 'date-display-single', 'property': 'dc:date'}
            )['content']

            self.pub_date = dateutil.parser.parse(pub_date_raw).date()
        except (TypeError, ValueError):
            # Either 'None is not subscriptable' or the date is ill formatted
            self.pub_date = None

        try:
            request_committee_top = self.soup.find('div', {'class': 'summary'})
            request_committee_text = request_committee_top.get_text()

            self.committee = COMMITTEE_NAME.search(request_committee_text).group(1)
        except AttributeError:
            # group() / get_text() are not defined on None
            self.committee = None

        try:
            request_date_raw = LONG_DATE.search(request_committee_text).group()
            self.request_date = dateutil.parser.parse(request_date_raw).date()
        except (AttributeError, ValueError):
            # Bad date, or else group() is not defined on None
            self.request_date = None

        if self.request_date is not None:
            self.congress = compute_congress(self.request_date)
        else:
            self.congress = None

    def _get_dollar_contexts(self):
        """
        Returns a list of "word contexts" that surround likely cost contexts.
        """
        contexts = []
        try:
            article_pars = self.soup.article.find_all('p')
        except AttributeError:
            article_pars = []

        for paragraph in article_pars:
            paragraph_text = paragraph.get_text()
            for match in COST_CONTEXT.finditer(paragraph_text):
                contexts.append(match.group())

        return contexts

if __name__ == '__main__':
    import sys
    if len(sys.argv) == 1:
        print(sys.argv[0], '<url>')
        sys.exit(1)

    info = Bill(sys.argv[1])
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
