# Taipower Data Backup

This project archives and visualizes power generation data from Taiwan Power Company (Taipower). It regularly fetches data from [Taipower's Generation Data Page](https://www.taipower.com.tw/d006/loadGraph/loadGraph/genshx_.html) and provides an alternative viewer at [https://tainan.olc.tw/p/taipower/](https://tainan.olc.tw/p/taipower/).

## Features

- Automated data collection from Taipower's generation data
- Historical data archival in JSON format
- Data visualization interface
- Daily backups through Git

## Data Structure

Data is stored in the following structure:
```
docs/
  ├── genary.json            # Latest data snapshot
  └── genary/               # Historical data archive
      └── YYYY/            # Year
          └── YYYYMMDD/    # Date
              ├── HHMMSS.json  # Time-based data files
              └── list.json    # Index of available times
```

## Scripts

- `scripts/cron.php`: Automated data collection and Git backup
- `scripts/01_fetch_genary.php`: Fetches current generation data from Taipower
- `scripts/02_finddata.php`: Data processing utility for specific power sources

## Setup

1. Clone this repository
2. Ensure PHP and Git are installed
3. Set up a cron job to run `scripts/cron.php` at desired intervals
4. Configure Git credentials for automated commits

## License

This project is open source and available under the MIT License.

## Data Source

Data is sourced from Taiwan Power Company's public data interface. Please refer to their terms of use for data usage rights.
