"""
Scrapes the given CBO page, providing all the dollar figures found in the page
and the context around them.
"""
import re
import sys
from urllib.request import urlopen

from bs4 import BeautifulSoup

def split_sentences(text):
    """
    Splits a paragraph into words, splitting on sentence and word delimiters.
    """
    sentences = re.split('[.!?]+', text)
    return sentences

def get_dollar_contexts(url):
    """
    Retrieves the given CBO page, and returns a list of contexts with dollar
    figures in them.

    Each context is a 3-tuple: (left, budget, right)

    - left is a list of words preceding the budget figure
    - budget is the dollar figure
    - right is a list of words after the budget figure
    """
    with urlopen(url) as page:
        soup = BeautifulSoup(page.read(), 'html.parser')

    contexts = []
    article_pars = soup.article.find_all('p')

    for paragraph in article_pars:
        paragraph_text = paragraph.get_text()
        if '$' in paragraph_text:
            sentences = split_sentences(paragraph_text)
            for sentence in sentences:
                if '$' in sentence:
                    contexts.append(sentence)

    return contexts 

if len(sys.argv) == 1:
    print(sys.argv[0], '<url>')
    sys.exit(1)

for sentence in get_dollar_contexts(sys.argv[1]):
    print(sentence)
