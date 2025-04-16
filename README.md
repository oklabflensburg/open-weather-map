# DWD Stationslexikon


Hier soll eine interaktive Karte mit den Wetterdaten des Deutschen Wetterdienstes auf Basis des Stationslexikons vom Deutschen Wetterdienst entstehen.



```sh
cd tools
source venv/bin/activate
pip3 install -r requirements.txt
python3 insert_mosmix_stations.py --env ../.env --src ../data/mosmix_stations.csv --verbose
deactivate
```


---


## How to Contribute

Contributions are welcome! Please refer to the [CONTRIBUTING.md](CONTRIBUTING.md) guide for details on how to get involved.


---


## License

This repository is licensed under [CC0-1.0](LICENSE).
