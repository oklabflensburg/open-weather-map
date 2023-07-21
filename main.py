#!./venv/bin/python

import psycopg2

from fastapi import FastAPI
from pydantic_settings import BaseSettings
 

class Settings(BaseSettings):
    app_name: str = 'Open accident map'
    db_host: str
    db_user: str
    db_name: str
    db_port: int
    db_pass: str

    class Config:
        env_file = '.env'


app = FastAPI()
setting = Settings()


db_connection = psycopg2.connect(
    host = setting.db_host,
    port = setting.db_port,
    dbname = setting.db_name,
    password = setting.db_pass,
    user = setting.db_user
)

db_connection.set_session(readonly = True)
cur = db_connection.cursor()


@app.get('/stations')
async def get_all_stations():
    db_query = '''
    SELECT jsonb_build_object(
        'type', 'FeatureCollection',
        'features', jsonb_agg(fc.feature)
    )
    FROM (
        SELECT jsonb_build_object(
            'type', 'Feature',
            'geometry', ST_AsGeoJSON(ST_Transform(s.geom, 4326))::jsonb,
            'properties', jsonb_build_object('station_id', to_jsonb(s.station_id),
            'start_date', to_jsonb(s.start_date), 'end_date', to_jsonb(s.end_date),
            'height', to_jsonb(s.height), 'city_name', to_jsonb(s.city_name),
            'county_name', to_jsonb(s.county_name))
        ) AS feature
        FROM stations AS s
    ) AS fc;
    '''

    cur.execute(db_query)

    results = cur.fetchall()

    if len(results) > 0:
        return results[0][0]
    else:
        return {}
