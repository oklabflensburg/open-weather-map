import click
import sys
import csv
from typing import List
import xml.etree.ElementTree as ET
from dataclasses import dataclass


@dataclass
class Station:
    '''Class to store station information.'''
    id: str
    name: str
    latitude: float
    longitude: float
    elevation: float
    icao_code: str = ""


def parse_mosmix_kml(kml_file: str) -> List[Station]:
    '''Parse the KML file and extract station information.

    Args:
        kml_file: Path to the KML file.

    Returns:
        List of Station objects.
    '''
    try:
        namespaces = {
            'kml': 'http://www.opengis.net/kml/2.2',
            'dwd': (
                'https://opendata.dwd.de/weather/lib/'
                'pointforecast_dwd_extension_V1_0.xsd'
            )
        }

        tree = ET.parse(kml_file)
        root = tree.getroot()

        # Find all Placemark elements which contain station information
        placemarks = root.findall('.//kml:Placemark', namespaces)

        stations = []
        for placemark in placemarks:
            # Extract station name
            name_elem = placemark.find('./kml:description', namespaces)
            name = name_elem.text if name_elem is not None else "Unknown"

            # Extract station ID
            id_elem = placemark.find('./kml:name', namespaces)
            station_id = id_elem.text if id_elem is not None else "Unknown"

            icao_code = ''

            # Extract coordinates
            coords_elem = placemark.find('.//kml:coordinates', namespaces)
            if coords_elem is not None and coords_elem.text:
                # Coordinates are in format: longitude,latitude,elevation
                lon, lat, elev = map(float, coords_elem.text.split(','))
                stations.append(Station(id=station_id, name=name,
                                latitude=lat, longitude=lon, elevation=elev,
                                icao_code=icao_code))

        return stations
    except Exception as e:
        print(f"Error parsing KML file: {e}", file=sys.stderr)
        return []


@click.command(help='Parse DWD MOSMIX KML files.')
@click.argument('kml_file', type=click.Path(exists=True))
@click.option(
    '-o', '--output',
    type=click.Choice(['text', 'csv', 'json'], case_sensitive=False),
    default='text',
    help='Output format (text, csv, json)'
)
@click.option(
    '-f', '--output-file',
    type=click.Path(),
    help='Write output to a file instead of stdout'
)
def main(kml_file, output, output_file):
    '''
    Main function to parse command line arguments and execute the parsing.
    '''
    stations = parse_mosmix_kml(kml_file)

    if not stations:
        click.echo("No stations found or error parsing the file.", err=True)
        return 1

    if output.lower() == 'text':
        click.echo(f"Found {len(stations)} stations:")
        for station in stations:
            icao_info = (
                f", ICAO: {station.icao_code}" if station.icao_code else ""
            )
            click.echo(f"ID: {station.id}, Name: {station.name}{icao_info}, "
                       f"Lat: {station.latitude}, Lon: {station.longitude}, "
                       f"Elev: {station.elevation}m")
    elif output.lower() == 'csv':
        if output_file:
            try:
                with open(output_file, 'w', newline='') as f:
                    csv_writer = csv.writer(f, quoting=csv.QUOTE_ALL)
                    csv_writer.writerow(["id", "name", "latitude", "longitude", "elevation", "icao_code"])
                    for station in stations:
                        csv_writer.writerow([
                            station.id, station.name, station.latitude,
                            station.longitude, station.elevation, station.icao_code
                        ])
                click.echo(f"CSV data written to {output_file}")
            except IOError as e:
                click.echo(f"Error writing to file {output_file}: {e}", err=True)
                return 1
        else:
            output_buffer = sys.stdout
            csv_writer = csv.writer(output_buffer, quoting=csv.QUOTE_ALL)
            csv_writer.writerow(["id", "name", "latitude", "longitude", "elevation", "icao_code"])
            for station in stations:
                csv_writer.writerow([
                    station.id, station.name, station.latitude,
                    station.longitude, station.elevation, station.icao_code
                ])
    elif output.lower() == 'json':
        import json
        click.echo(json.dumps([vars(s) for s in stations], indent=2))
    else:
        click.echo(f"Unknown output format: {output}", err=True)
        return 1

    return 0


if __name__ == "__main__":
    sys.exit(main())
