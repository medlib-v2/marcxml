## MarcXML Parser ##

`MarcXML` is currently a minimal MARC21/XML parser for use with `QuiteSimpleXMLElement`,
with support for the MARC21 Bibliographic, Authority and Holdings formats.

### Installation Laravel ###

You can install this package by using [Composer](http://getcomposer.org), running this command:
```bash
    composer require medlib/marcxml
```

The next required step is to add the service provider to config/app.php :
```php
    Medlib\MarcXML\Providers\ParserServiceProvider::class
```
## Example: ##

```php

use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement,
    Medlib\MarcXML\Parser\Parser;

$data = file_get_contents('http://sru.bibsys.no/search/biblio?' . http_build_query(array(
	'version' => '1.2',
	'operation' => 'searchRetrieve',
	'recordSchema' => 'marcxchange',
	'query' => 'bs.isbn="0-521-43291-x"'
)));

$doc = new QuiteSimpleXMLElement($data);
$doc->registerXPathNamespaces(array(
        'srw' => 'http://www.loc.gov/zing/srw/',
        'marc' => 'http://www.loc.gov/MARC21/slim',
        'd' => 'http://www.loc.gov/zing/srw/diagnostic/'
    ));

$parser = new Parser();

$record = $parser->parse($doc->first('/srw:searchRetrieveResponse/srw:records/srw:record/srw:recordData/marc:record'));

print $record->title;

foreach ($record->subjects as $subject) {
	print $subject['term'] . '(' . $subject['system'] . ')';
}
```
# Transformation/normalization

This parser is aimed at producing machine actionable output, and does some non-reversible 
transformations to achieve this. Transformation rules expect AACR2-like records, and are
tested mainly against the Norwegian version of AACR2 (*Norske katalogregler*), but might
work well with other editions as well.

Examples:

 - `title` is a combination of 300 $a and $b, separated by ` : `.
 - `year` is an integer extracted from 260 $c by extracting the first four digit integer found
   (`c2013` → `2013`, `2009 [i.e. 2008]` → `2009` (this might be a bit rough…))
 - `pages` is an integer extracted from 300 $a. The raw value, useful for e.g. non-verbal content,
   is stored in `extent`
 - `creators[].name` are transformed from '`Lastname`, `Firstname`' to '`Firstname Lastname`'

# Form and material

Form and material is encoded in the leader and in control fields 006, 007 and 008.
Encoding this information in a format that makes sense is a *work-in-progress*.

Electronic and printed material is currently distinguished using the boolean valued `electronic` key.

Printed book:

```json
{
	"material": "book",
	"electronic": false
}
```

Electronic book:

```json
{
	"material": "book",
	"electronic": true
}
```

Congratulations, you have successfully installed Yaz Query Builder !

