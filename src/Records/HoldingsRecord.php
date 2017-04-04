<?php

namespace Medlib\MarcXML\Records;

use Danmichaelo\QuiteSimpleXmlElement\QuiteSimpleXmlElement;

/**
 * @property int       $id                 Local record identifier
 * @property string    $barcode
 * @property string    $status
 * @property string    $location
 * @property string    $sublocation
 * @property string    $shelvinglocation
 * @property string    $callcode
 * @property string    $use_restrictions
 * @property string    $circulation_status
 * @property string    $fulltext
 * @property string[]  $nonpublic_notes
 * @property string[]  $public_notes
 * @property array     $holdings
 * @property \Carbon\Carbon    $acquired
 * @property \Carbon\Carbon    $modified
 * @property \Carbon\Carbon    $created
 */
class HoldingsRecord extends Record
{
    /**
     * 859 $f: Use restrictions / Tilgjengelighet
     * @ref: http://www.bibsys.no/files/out/biblev/utlaanstatus-marc21.pdf
     * @see http://norzig.no/profiles/holdings2.html#tab1
     */
    public static $m859_f = [
        '1' => 'Not for loan',
        '2' => 'In-library use only',
        '3' => 'Overnight only',
        '4' => 'Use only in controlled access room',
        '5' => 'Renewals not permitted',
        '6' => 'Short loan period',
        '7' => 'Normal loan period',
        '8' => 'Long loan period',
        '9' => 'Term loan',
        '10' => 'Semester loan',
        '11' => 'Available for supply without return',
        '12' => 'Not for ILL',
        '13' => 'Not for User ILL',
    ];

    /**
     * 859 $h: Circulation status  / Utlånsstatus
     * @eef: http://www.bibsys.no/files/out/biblev/utlaanstatus-marc21.pdf
     * @see http://norzig.no/profiles/holdings2.html#tab2
     */
    public static $m859_h = [
        '0' => 'Available',
        '1' => 'Circulation status undefined',
        '2' => 'On order',
        '3' => 'Not available; undefined',
        '4' => 'On loan',
        '5' => 'On loan and not available for recall until earliest recall date',
        '6' => 'In process',
        '7' => 'Recalled',
        '8' => 'On hold',
        '9' => 'Waiting to be made available',
        '10' => 'In transit (between library locations)',
        '11' => 'Claimed returned or never borrowed',
        '12' => 'Lost',
        '13' => 'Missing, being traced',
        '14' => 'Supplied (i.e. return not required',
        '15' => 'In binding',
        '16' => 'In repair',
        '17' => 'Pending transfer',
        '18' => 'Missing, overdue',
        '19' => 'Withdrawn',
        '20' => 'Weeded',
        '21' => 'Unreserved',
        '22' => 'Damaged',
        '23' => 'Non circulating',
        '24' => 'Other',
    ];

    /**
     * @param \Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement $data
     */
    public function __construct(QuiteSimpleXmlElement $data = null)
    {
        if (is_null($data)) {
            return;
        }

        $this->id = $data->text('marc:controlfield[@tag="001"]');

        $this->bibliographic_record = $this->parseExchangeChars($data->text('marc:controlfield[@tag="004"]')) ?: null;

        $fulltext = array();
        $nonpublic_notes = array();
        $public_notes = array();

        /**
         * 008: Extract datestamp only
         */
        $f008 = $data->text('marc:controlfield[@tag="008"]');
        $this->created = $this->parseDateTime(substr($f008, 0, 6));

        /**
         * 009: Reserved for local use
         */
        $this->status = $this->parseExchangeChars($data->text('marc:controlfield[@tag="009"]'));

        foreach ($data->all('marc:datafield') as $node) {

            $marcfield = intval($node->attributes()->tag);

            switch ($marcfield) {

                case 852:
                    /**
                     * @see http://www.loc.gov/marc/holdings/concise/hd852.html
                     */
                    $this->location = $this->parseExchangeChars($node->text('marc:subfield[@code="a"]'));
                    $this->sublocation = $this->parseExchangeChars($node->text('marc:subfield[@code="b"]'));
                    $this->shelvinglocation = $this->parseExchangeChars($node->text('marc:subfield[@code="c"]'));
                    $this->callcode = $this->parseExchangeChars($node->text('marc:subfield[@code="h"]'));

                    if (($x = $this->parseExchangeChars($node->text('marc:subfield[@code="x"]'))) !== '') {
                        $nonpublic_notes[] = $x;
                    }
                    if (($x = $this->parseExchangeChars($node->text('marc:subfield[@code="z"]'))) !== '') {
                        $public_notes[] = $x;
                    }

                    break;

                case 856:
                    $description = $this->parseExchangeChars($node->text('marc:subfield[@code="3"]'));
                    if (in_array($description, array('Fulltekst', 'Fulltext'))) {
                        $fulltext[] = array(
                            'url' => $this->parseExchangeChars($node->text('marc:subfield[@code="u"]')),
                            'linktext' => $this->parseExchangeChars($node->text('marc:subfield[@code="y"]')),
                            'comment' => $this->parseExchangeChars($node->text('marc:subfield[@code="z"]')),
                        );
                    }
                    break;

                case 859:
                    /**
                     * 859: Forslag til norsk tillegg til MARC 21 for utlånsstatus
                     * @see http://www.bibsys.no/files/out/biblev/utlaanstatus-marc21.pdf
                     * 859 $f: Use restrictions / Tilgjengelighet
                     */
                    $x = $this->parseExchangeChars($node->text('marc:subfield[@code="f"]'));
                    if ($x !== '') {
                        if (isset(self::$m859_f[$x])) {
                            $this->use_restrictions = self::$m859_f[$x];
                        }
                    }

                    $x = $this->parseExchangeChars($node->text('marc:subfield[@code="h"]'));
                    if ($x !== '') {
                        if (isset(self::$m859_h[$x])) {
                            $this->circulation_status = self::$m859_h[$x];
                        }
                    }

                    break;

                case 866:
                    /**
                     * 866: Textual Holdings-General Information
                     */
                    $this->holdings = $this->parseExchangeChars($node->text('marc:subfield[@code="a"]'));

                    break;

                case 876:
                    /**
                     * 866: Item Information - Basic Bibliographic Unit
                     * $d - Date acquired (R)
                     */
                    $this->acquired = $this->parseDateTime($node->text('marc:subfield[@code="d"]'));
                    $this->barcode = $this->parseExchangeChars($node->text('marc:subfield[@code="p"]'));
                    $this->status = $this->parseExchangeChars($node->text('marc:subfield[@code="j"]'));
                    break;

            }
        }

        $this->fulltext = $fulltext;
        $this->nonpublic_notes = $nonpublic_notes;
        $this->public_notes = $public_notes;
    }
}
