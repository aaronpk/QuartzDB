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
foreach($results as $id=>$record) {
  echo $id . ": " . $record->property . "\n";
}
```

Last n records

```php
$results = $db->queryLast(10);
foreach($results as $id=>$record) {
  echo $id . ": " . $record->property . "\n";
}
```


Single Record

```php
$db->getByDate($date);
$db->getByID("YYYYMMDDline");
```

### Maintenance

```php
// Re-sorts all the data in the shard by date.
// This requires loading the entire file into memory.
$shard = $db->getShard("YYYY","MM","DD");
$shard->sort();
```


Filesystem
----------

One file per day.

The base path of the database contains a "data" folder and "index" folder.

```
/data/
     /2015/08/05.txt
     /2015/08/05.meta
     /2015/08/06.txt
     /2015/08/06.meta
     /2015/08/07.txt
     /2015/08/07.meta
```

Files contain one record per line, separated by newlines. The first 26 characters are
the date with microsecond precision, followed by a space, followed by the JSON record.

```
2015-08-03 09:00:00.000000 {"foo":"bar","value":300}
2015-08-03 09:00:01.000000 {"foo":"bar","value":300}
2015-08-03 09:00:02.000000 {"foo":"bar","value":300}
```

The ".meta" files are for storing information about the data file. Currently the only
thing in the meta file is line 1 contains the number of lines in the data file.

There is also a file in the root of the data folder containing the date of the last
date seen. This is for quickly retrieving the last n records in the database without
having to search the filesystem for all files.


Indexes
-------

Indexes are not yet implemented. Currently considering whether to implement a file-based index
similar to the mechanism of storing the raw data, or whether to defer to a real
database to handle the indexes.

For example, using ElasticSearch, SQLite, Postgres, or MySQL to store the indexes 
means being able to leverage a lot of existing work when searching and maintaining these
indexes. 

If indexes do end up being implemented here, they will probably work like this:

Indexes would be stored as files on disk

```
/index/
     /index_name/2015/07.txt
     /index_name/2015/08.txt
```

The file would contain pointers to the file and line number of each record matching
the index.

```
{
  "property": {
    "value1": ["2015/08/05/291","YYYY/MM/DD/line"],
    "value2": ["2015/08/05/292","YYYY/MM/DD/line"],
  }
}
```

To update the indexes for all entries this shard:

```
$shard = $db->getShard("YYYY","MM","DD");
$shard->reindex();
$shard->save();
```

