# Emergency Generator Backup System

This system monitors emergency generators and creates backups when they have active values (non-zero, non-empty output).

## Monitored Emergency Generators

The following generators are monitored for emergency activation using **partial matching** to handle dynamic notation changes:

**Exact Match Required:**
- 核二Gas1
- 核二Gas2
- 核三Gas1
- 核三Gas2
- 台中Gas1&2
- 台中Gas3&4
- 大林#5

**Partial Match (handles dynamic notation):**
- 興達#1 (matches 興達#1(註15), 興達#1(註12), etc.)
- 興達#2 (matches 興達#2(註15), 興達#2(註12), etc.)
- 興達#3 (matches 興達#3(註12), 興達#3(註15), etc.)
- 興達#4 (matches 興達#4(註12), 興達#4(註15), etc.)

The system uses intelligent pattern matching to handle cases where generator names include variable notation like `(註12)`, `(註15)`, etc., which may change over time.

## How It Works

1. **Automatic Monitoring**: The `03_emergency_backup.php` script runs automatically via cron every time new data is fetched
2. **Detection Logic**: Checks if any emergency generators have values that are not empty, "0.0", "0", or "-"
3. **Backup Creation**: When active emergency generators are detected, creates backup files in `docs/emergency/YYYY/YYYYMMDD/` format
4. **Data Retention**: Automatically cleans up backup files older than 90 days

## File Structure

```
docs/emergency/
├── 2025/
│   └── 20250611/
│       ├── 213000.json    # Backup data for 21:30:00
│       └── index.json     # Summary index for the day
```

## Scripts

### `scripts/03_emergency_backup.php`
Main backup script that:
- Scans current generator data for emergency activations
- Creates timestamped backup files
- Maintains daily index files
- Cleans up old backups

### `scripts/04_parse_historical_emergency.php`
Historical data parser that:
- Processes all existing genary JSON files (10,000+ files)
- Generates emergency backup files for historical data
- Creates comprehensive timeline of emergency activations
- Includes performance optimizations and error handling
- Generates statistical summary reports

### `scripts/view_emergency_logs.php`
Utility script to view recent emergency activations:
```bash
# View last 7 days (default)
php scripts/view_emergency_logs.php

# View last 30 days
php scripts/view_emergency_logs.php 30

# View last 90 days
php scripts/view_emergency_logs.php 90
```

## Backup Data Format

Each backup file contains:
```json
{
    "timestamp": "2025-06-11 21:30",
    "active_emergency_generators": [
        {
            "name": "大林#5",
            "pattern": "大林#5",
            "output": "285.9",
            "percentage": "-",
            "status": "",
            "timestamp": "2025-06-11 21:30"
        }
    ],
    "total_count": 1,
    "created_at": "2025-06-11 13:55:30"
}
```

**Fields Explanation:**
- `name`: Actual generator name as it appears in the data (including any notation)
- `pattern`: The matched pattern used for detection (useful for grouping and analysis)
- `output`: Power output in MW
- `percentage`: Load percentage (if available)
- `status`: Current status (e.g., "定期測試", "測試運轉")
- `timestamp`: Data timestamp

## Integration

The emergency backup system is automatically integrated into the existing cron job pipeline:
1. `01_fetch_genary.php` - Fetches latest data
2. `03_emergency_backup.php` - Checks for emergency activations
3. Git commit and push

## Historical Data Processing

To process all historical data and generate emergency backups:
```bash
# Parse all historical files (10,000+ files)
# This may take several minutes to complete
php scripts/04_parse_historical_emergency.php
```

## Manual Testing

To manually test the system:
```bash
# Run emergency backup check
php scripts/03_emergency_backup.php

# View recent emergency logs
php scripts/view_emergency_logs.php

# Process historical data
php scripts/04_parse_historical_emergency.php
```

## Results Summary

The historical processing has identified thousands of emergency generator activations, including:
- **核三Gas1**: Testing activations (定期測試) on 2025-03-25
- **核三Gas2**: Testing activations on 2025-03-27  
- **興達#3**: Extended emergency operations on 2025-04-07 to 2025-04-08
- **興達#4**: Emergency activations on 2025-04-20
- **核二Gas2**: Testing activation on 2025-04-14
- **大林#5**: Ongoing emergency operations throughout recent months

Emergency events are often marked with status indicators like "定期測試" (routine testing), "測試運轉" (test operation), or other operational reasons.

## Pattern Matching Features

The system now uses intelligent pattern matching that:
- **Handles dynamic notation**: Automatically adapts to changing generator notations like `(註12)`, `(註15)`, `(註99)`, etc.
- **Maintains data integrity**: Preserves original generator names while using patterns for matching
- **Future-proof design**: Will continue to work even if notation formats change
- **Comprehensive coverage**: Matches variations like `興達#1`, `興達#1(註15)`, `興達#1(新機組)`, etc.

This ensures the emergency backup system remains robust and continues to function correctly as the power grid data format evolves.