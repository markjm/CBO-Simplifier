"""
Scrapes the given CBO page, providing all the dollar figures found in the page
and the context around them.
"""
import re
import sys
from urllib.request import urlopen

from bs4 import BeautifulSoup

REGEX = re.compile(r'(\w+\W){0,10}(\$[0-9,]+(\.[0-9]+)?)\W(billion|million|thousand)+\Wover\Wthe\W[0-9]+-[0-9]+\Wperiod')

def split_sentences(text):
    """
    Splits a paragraph into words, splitting on sentence and word delimiters.
    """
    sentences = re.split('[.!?]+', text)
    return sentences

def get_dollar_contexts(url):
    """
    Retrieves the given CBO page, and returns a list of sentences with dollar
    figures in them.
    """
    with urlopen(url) as page:
        soup = BeautifulSoup(page.read(), 'html.parser')

    contexts = []
    article_pars = soup.article.find_all('p')

    for paragraph in article_pars:
        paragraph_text = paragraph.get_text()
        for match in REGEX.finditer(paragraph_text):
            print(match.group())

    return contexts 

if len(sys.argv) == 1:
    print(sys.argv[0], '<url>')
    sys.exit(1)

for sentence in get_dollar_contexts(sys.argv[1]):
    print(sentence)
