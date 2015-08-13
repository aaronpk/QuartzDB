QuartzDB
========

A flat-file database optimized to hold time-series data.

When should I use this?
-----------------------

Some great uses for QuartzDB:

* Storing GPS logs (just store your records as GeoJSON!)
* Storing sensor logs from IoT devices, such as in-house environment sensors


You should use this if...
* ... you believe text files are easier to back up and keep multiple copies of
* ... you believe text files are more likely than binary database files to last long-term


API
---

```php
$db = new Quartz\DB('/path/to/folder', 'w');
$db = new Quartz\DB('/path/to/folder', 'r');
```

### Adding Records

```php
$db->add($date, $data);
```

This will add the entry always to the end of the appropriate file, since it is expected
that data will be inserted in order. If you need to insert out of order, you can run
the maintenance task to re-sort the date's file when needed.


### Querying

Range

```php
$results = $db->queryRange($fromDate, $toDate);
// Loop through the result set.
// The JSON is parsed when the row is accessed.
foreach($results as $date=>$record) {
  echo $date . ": " . $record->property . "\n";
}
```

Single Record

```php
$db->getByDate($date);
$db->getByID("YYYY-MM-DD-line");
```

### Maintenance

```php
// Re-sorts all the data in the shard by date
$shard = $db->getShard("YYYY","MM","DD");
$shard->sort();
$shard->save();

// Updates the indexes for all entries this shard
$shard = $db->getShard("YYYY","MM","DD");
$shard->reindex();
$shard->save();
```


Filesystem
----------

One file per day.

The base path of the database contains a "data" folder and "index" folder.

```
/data/
     /2015/08/05.txt
     /2015/08/06.txt
     /2015/08/07.txt
/index/
     /index_name/2015/07.txt
     /index_name/2015/08.txt
```

Files contain one record per line, separated by newlines. The first 23 characters are
the date with microsecond precision, followed by a space, followed by the JSON record.

```
2015-08-03 09:00:00.000000 {"foo":"bar","value":300}
2015-08-03 09:00:01.000000 {"foo":"bar","value":300}
2015-08-03 09:00:02.000000 {"foo":"bar","value":300}
```

Indexes

```
{
  "property": {
    "value1": ["2015/08/05/291","YYYY/MM/DD/line"],
    "value2": ["2015/08/05/292","YYYY/MM/DD/line"],
  }
}
```

TODO: Consider using SQLite, Postgres, MySQL or Redis to store indexes instead.

