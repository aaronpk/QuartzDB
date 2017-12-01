
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
