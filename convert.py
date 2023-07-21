#!./venv/bin/python

import os
import csv
import psycopg2

from psycopg2 import sql
from datetime import datetime
from psycopg2.errors import DuplicateDatabase
from os.path import join, dirname
from dotenv import load_dotenv
from shapely import wkb, wkt


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
except Exception as e:
    print(e)


conn.autocommit = True
cursor = conn.cursor()


def insert_stations(cursor):
    with open('./data/KL_Tageswerte_Beschreibung_Stationen.csv', 'r') as f:
        csvreader = csv.reader(f)

        next(csvreader)

        for row in csvreader:
            geometry = wkt.loads(f'POINT({row[5]} {row[4]})')
            geometry_object = wkb.dumps(geometry, hex=True, srid=4326)

            start_date_object = datetime.strptime(row[1], '%Y%m%d')
            end_date_object = datetime.strptime(row[2], '%Y%m%d')

            try:
                sql = '''
                    INSERT INTO stations (station_id, start_date, end_date, height, city_name, county_name, geom)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                '''

                cursor.execute(sql, (int(row[0]), start_date_object, end_date_object,
                            int(row[3]), row[6], row[7], geometry_object))
            except Exception as e:
                print(e)
                continue


def prepare_database(cursor, db_name):
    try:
        cursor.execute(sql.SQL('CREATE DATABASE {}').format(sql.Identifier(db_name)))
    except DuplicateDatabase:
        pass


def create_table(cursor):
    try:
        cursor.execute('CREATE EXTENSION postgis;')
    except Exception as e:
        print(e)

    try:
        cursor.execute(sql.SQL('''
            CREATE TABLE stations (
                station_id INTEGER PRIMARY KEY,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                height INTEGER NOT NULL,
                city_name VARCHAR(255) NOT NULL,
                county_name VARCHAR(255) NOT NULL,
                geom GEOMETRY NOT NULL
            )
        '''))
    except Exception as e:
        print(e)


def main():
    prepare_database(cursor, 'weather-data')
    create_table(cursor)
    insert_stations(cursor)


if __name__ == '__main__':
    main()
