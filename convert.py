#!./venv/bin/python

import os
import csv
import psycopg2

from psycopg2 import sql
from psycopg2.errors import DuplicateDatabase
from os.path import join, dirname
from dotenv import load_dotenv


dotenv_path = join(f'{dirname(__file__)}', '.env')
load_dotenv(dotenv_path)


try:
    conn = psycopg2.connect(
        database = os.getenv('DB_NAME'),
        password = os.getenv('DB_PASS'),
        user = os.getenv('DB_USER'),
        host = os.getenv('DB_HOST'),
        port = os.getenv('DB_PORT')
    )
except:
    print('Unable to connect to the database')


conn.autocommit = True
cursor = conn.cursor()


with open('./data/KL_Tageswerte_Beschreibung_Stationen.csv', 'r') as f:
    csvreader = csv.reader(f)

    next(csvreader)

    for row in csvreader:
        print(row)


def prepare_database(db_name):
    try:
        cursor.execute(sql.SQL('CREATE DATABASE {}').format(sql.Identifier(db_name)))
    except DuplicateDatabase:
        pass



