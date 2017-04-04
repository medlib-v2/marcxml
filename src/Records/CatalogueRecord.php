<?php

namespace Medlib\MarcXML\Records;

use Carbon\Carbon;
use Danmichaelo\QuiteSimpleXmlElement\QuiteSimpleXmlElement;

/**
 * @property int       $id                 Local record identifier
 * @property string    $material
 * @property boolean   $electronic
 * @property boolean   $is_series
 * @property boolean   $is_multivolume
 * @property boolean
 * @property string    $agency
 * @property string    $lccn
 * @property string    $catalogingRules
 * @property string    $title
 * @property string    $alternativeTitles
 * @property string    $part_no
 * @property string    $part_name
 * @property string    $part_of
 * @property string    $medium
 * @property string    $edition
 * @property string    $placeOfPublication
 * @property string    $publisher
 * @property string    $extent
 * @property string    $contents
 * @property string    $summary
 * @property string    $cover_image
 * @property string    $description
 * @property string    $preceding
 * @property string    $succeeding
 * @property string[]  $isbns
 * @property string[]  $issns
 * @property string[]  $notes
 * @property array     $series
 * @property array     $other_form
 * @property array     $creators
 * @property array     $meetings
 * @property array     $subjects
 * @property array     $genres
 * @property array     $classifications
 * @property array     $debug
 * @property int       $pages
 * @property int       $year
 * @property Carbon    $modified
 * @property Carbon    $created
 */
class CatalogueRecord extends Record
{
    /**
     * @param \Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement $data
     */
    public function __construct(QuiteSimpleXmlElement $data = null)
    {
        if (is_null($data)) {
            return;
        }

        $this->parseMaterial($data);

        // Control fields
        $this->id = $data->text('marc:controlfield[@tag="001"]');

        /**
         * 003: MARC code for the agency whose system control number is
         * contained in field 001 (Control Number)
         * @see http://www.loc.gov/marc/authority/ecadorg.html
         */
        $this->agency = $data->text('marc:controlfield[@tag="003"]');

        // 005: Modified
        $this->modified = $this->parseDateTime($data->text('marc:controlfield[@tag="005"]'));

        // 008: Extract *some* information
        $f008 = $data->text('marc:controlfield[@tag="008"]');
        $this->created = $this->parseDateTime(substr($f008, 0, 6));

        $creators = [];
        $meetings = [];
        $subjects = [];
        $genres = [];
        $classifications = [];
        $series = [];
        $notes = [];
        $isbns = [];
        $issns = [];
        $alternativeTitles = [];

        // Relationships
        $preceding = [];
        $succeeding = [];
        $part_of = [];
        $other_form = [];

        $this->is_series = false;
        $this->is_multivolume = false;

        foreach ($data->all('marc:datafield') as $node) {

            $marcfield = intval($node->attributes()->tag);

            switch ($marcfield) {
                /**
                // 010 - Library of Congress Control Number (NR)
                case 10:
                $this->lccn = $node->text('marc:subfield[@code="a"]');
                break;
                 */
                // 010 - ISBN (International Standard Book Number)
                case 10:
                    $isbn = $node->text('marc:subfield[@code="a"]');
                    $isbn = preg_replace('/^([0-9\-xX]+).*$/', '\1', $isbn);
                    if (empty($isbn)) {
                        break;
                    }
                    array_push($isbns, $isbn);
                    break;

                // 011 - ISSN (International Standard Serial Number)
                case 11:
                    $issn = $node->text('marc:subfield[@code="a"]');
                    $issn = preg_replace('/^([0-9\-xX]+).*$/', '\1', $issn);
                    if (empty($issn)) {
                        break;
                    }
                    array_push($issns, $issn);
                    break;

                // 013 - ISMN (International Standard Music Number)
                case 13:
                    var_dump("013 - ISMN");
                    break;

                // 015 - ISRN (International Standard technical Report Number)
                case 15:
                    var_dump("015 - ISRN");
                    break;

                // 016 ISRC (International Standard Recording Code)
                case 18:
                    var_dump("016 - ISRC");
                    break;
                // 022 - Numéro de publication officielle
                case 22:
                    $issn = $node->text('marc:subfield[@code="a"]');
                    $issn = preg_replace('/^([0-9\-xX]+).*$/', '\1', $issn);
                    if (empty($issn)) {
                        break;
                    }
                    array_push($issns, $issn);
                    break;

                // 039 - Numéro de notice récupérée d'un ancien système BnF
                case 39:
                    $source = $node->text('marc:subfield[@code="o"]');
                    $numCtrl = $node->text('marc:subfield[@code="a"]');
                    $dateUpDate = $node->text('marc:subfield[@code="d"]');
                    var_dump($source, $numCtrl, $dateUpDate);
                    break;

                // 040 - Cataloging Source (NR)
                case 40:
                    $x = $node->text('marc:subfield[@code="e"]');
                    if ($x) {
                        $this->catalogingRules = $x;
                    }
                    /** @see http://www.loc.gov/standards/sourcelist/descriptive-conventions.html */
                    break;

                // 071 Référence éditoriale
                case 71:
                    $analytique = $node->text('marc:subfield[@code="9a"]');
                    $num = $node->text('marc:subfield[@code="a"]');
                    $src = $node->text('marc:subfield[@code="b"]');
                    $qualif = $node->text('marc:subfield[@code="c"]');
                    $price = $node->text('marc:subfield[@code="d"]');
                    var_dump($analytique, $num, $src, $qualif, $price);
                    break;
                // 060 - National Library of Medicine Call Number (R)
                case 60:
                    $this->addClassification(
                        $node,
                        $classifications,
                        [
                            'a' => 'number',
                        ],
                        $system = 'nlm',
                        $assigner = ($node->attr('ind2') == '0') ? 'DNLM' : null
                    );
                    break;

                // 080 - Universal Decimal Classification Number (R)
                case 80:
                    $this->addClassification(
                        $node,
                        $classifications,
                        [
                            'a' => array('number', '^.*?([0-9.\/:()]+).*$', '\1'),
                            '2' => 'edition',
                        ],
                        $system = 'udc'
                    );
                    break;

                // 082 - Dewey Decimal Classification Number (R)
                case 82:
                    $this->addClassification(
                        $node,
                        $classifications,
                        [
                            'a' => array('number', '^.*?([0-9.]+)\/?([0-9.]*).*$', '\1\2'),
                            '2' => 'edition',
                            'q' => 'assigner',
                        ],
                        $system = 'ddc'
                    );
                    break;

                // 084 - Other Classification Number (R)
                case 84:
                    $this->addClassification(
                        $node,
                        $classifications,
                        [
                            'a' => 'number',
                            '2' => 'system',
                            'q' => 'assigner',
                        ]
                    );
                    break;

                case 89:
                    if (!isset($this->klass)) $this->klass = array();
                    $klass = $node->text('marc:subfield[@code="a"]');
                    $klass = preg_replace('/[^0-9.]/', '', $klass);

                    foreach ($this->klass as $kitem) {

                        if (($kitem['kode'] == $klass) && ($kitem['system'] == 'dewey')) {
                            continue 3;
                        }
                    }
                    array_push($this->klass, ['kode' => $klass, 'system' => 'dewey']);
                    break;

                case 100:
                    $this->parseDataGeneral($node);
                    break;

                case 102 :
                    /**
                     * Country of publication
                     */
                    $country = $this->getCountryByCode($node->text('marc:subfield[@code="a"]'));
                    $pattern = ['XX', 'ZZ'];
                    if((in_array($country, $pattern))) { $this->placeOfPublication = []; }
                    else { $this->placeOfPublication = $country; }

                    break;

                /**
                case 100:
                $author = [ 'name' => $node->text('marc:subfield[@code="a"]') ];

                $author['normalizedName'] = $author['name'];
                $spl = explode(', ', $author['name']);

                if (count($spl) == 2) {

                $author['name'] = $spl[1] . ' ' . $spl[0];
                }

                $this->parseRelator($node, $author, 'main');
                $this->parseAuthority($node->text('marc:subfield[@code="0"]'), $author);

                $creators[] = $author;
                break;
                 */
                case 110:
                    $author = array(
                        'name' => $node->text('marc:subfield[@code="a"]'),
                    );
                    $author['normalizedName'] = $author['name'];
                    foreach ($node->all('marc:subfield[@code="b"]') as $subunit) {
                        $author['name'] .= self::$subfieldSeparator . trim($subunit, ',');
                    }
                    $this->parseRelator($node, $author, 'corporate');
                    $this->parseAuthority($node->text('marc:subfield[@code="0"]'), $author);

                    $creators[] = $author;
                    break;

                case 111:
                    $meeting = [ 'name' => $node->text('marc:subfield[@code="a"]') ];
                    $meeting['normalizedName'] = $meeting['name'];
                    $this->parseRelator($node, $meeting, 'meeting');
                    $this->parseAuthority($node->text('marc:subfield[@code="0"]'), $meeting);

                    $meetings[] = $meeting;
                    break;
                case 130:
                    // Uniform title

                    // TODO: Need a more robust way to prefer 130 over 245
                    //       Currently we depend on 130 coming before 245.
                    $this->title = $node->text('marc:subfield[@code="a"]');
                    break;

                // 245 : Title Statement (NR)
                case 245:
                    $title = $node->text('marc:subfield[@code="a"]') . ' ' . $node->text('marc:subfield[@code="b"]');
                    $title = rtrim($title, ' /');

                    $titleParts = preg_split('/(:|=)/', $title, -1, PREG_SPLIT_DELIM_CAPTURE);
                    $isbdCode = ':';
                    $title = '';
                    foreach ($titleParts as $titlePart) {

                        $titlePart = trim($titlePart);

                        if (is_null($isbdCode)) {

                            $isbdCode = $titlePart;

                        } else {

                            if ($isbdCode == '=') {

                                $alternativeTitles[] = $titlePart;

                            } else {
                                /**
                                 * isbdCode == ':' or ';'
                                 * @see http://www.loc.gov/marc/bibliographic/bd245.html
                                 */
                                $title = empty($title) ? $titlePart : "$title $isbdCode $titlePart";
                            }
                            $isbdCode = null;
                        }
                    }

                    if (isset($this->title)) { // If not already set by 130
                        $alternativeTitles[] = $title;
                    } else {
                        $this->title = $title;
                    }

                    // $n : Number of part/section of a work (R)
                    $part_no = $node->text('marc:subfield[@code="n"]');
                    if ($part_no !== '') {
                        $this->part_no = $part_no;
                    }

                    // $p : Name of part/section of a work (R)
                    $part_name = $node->text('marc:subfield[@code="p"]');
                    if ($part_name !== '') {
                        $this->part_name = $part_name;
                    }

                    // $h : Medium (NR)
                    $medium = $node->text('marc:subfield[@code="h"]');
                    if ($medium !== '') {
                        $this->medium = $medium;
                    }

                    break;

                // 246 : Varying Form of Title (R)
                case 246:
                    // Note: The second indicator gives the type of title:
                    // 0 - Portion of title
                    // 1 - Parallel title
                    // 2 - Distinctive title
                    // 3 - Other title
                    // 4 - Cover title
                    // 5 - Added title page title
                    // 6 - Caption title
                    // 7 - Running title
                    // 8 - Spine title
                    $title = rtrim($node->text('marc:subfield[@code="a"]'), ' :-');
                    $subtitle = $node->text('marc:subfield[@code="b"]');

                    /** @see  http://www.loc.gov/marc/bibliographic/bd246.html */
                    if (!empty($subtitle)) {
                        $title .= ' : ' . $subtitle;
                    }
                    $alternativeTitles[] = $title;
                    break;

                case 250:
                    $this->edition = $node->text('marc:subfield[@code="a"]');
                    break;

                case 260:
                    $this->publisher = trim($node->text('marc:subfield[@code="b"]'), ':');
                    $y = preg_replace('/^.*?([0-9]{4}).*$/', '\1', $node->first('marc:subfield[@code="c"]'));
                    $this->year = $y ? intval($y) : null;
                    break;

                case 300:
                    $this->extent = trim($node->text('marc:subfield[@code="a"]'), ':');

                    # 2.5B2 "327 s.", 2.5B4 "48 [i.e. 96] s.", 2.5B7 "[93] s."
                    preg_match(
                        '/\[?([0-9]+)\]? (s.|p.|pp.)/',
                        $node->text('marc:subfield[@code="a"]'),
                        $matches
                    );
                    if ($matches) {
                        $this->pages = intval($matches[1]);
                    }

                    # 2.5B6 Eks: "s. 327-698" (flerbindsverk)
                    preg_match(
                        '/(s.|p.|pp.) ([0-9]+)-([0-9]+)/',
                        $node->text('marc:subfield[@code="a"]'),
                        $matches
                    );
                    if ($matches) {
                        $this->pages = intval($matches[3]) - intval($matches[2]) + 1;
                    }
                    break;

                case 490:
                    $serie = [
                        'title' => trim($node->text('marc:subfield[@code="a"]'), ':'),
                        'volume' => $node->text('marc:subfield[@code="v"]')
                    ];
                    $tmp_series = $this->series;
                    $tmp_series[] = $serie;
                    $this->series = $tmp_series;
                    break;

                // 500 : General Note (R)
                case 500:
                    // $a - General note (NR)
                    $notes[] = $node->text('marc:subfield[@code="a"]');
                    break;

                // 502 : Dissertation Note (R)
                case 502:
                    // $a - Dissertation note (NR)
                    $notes[] = $node->text('marc:subfield[@code="a"]');
                    break;

                case 505:
                    // $a -
                    $this->contents = $node->text('marc:subfield[@code="a"]');
                    break;

                case 520:
                    /**
                     * <datafield tag="520" ind1=" " ind2=" ">
                     *     <subfield code="a">"The conceptual changes brought by modern physics are important, radical and fascinating, yet they are only vaguely understood by people working outside the field. Exploring the four pillars of modern physics - relativity, quantum mechanics, elementary particles and cosmology - this clear and lively account will interest anyone who has wondered what Einstein, Bohr, Schro&#x308;dinger and Heisenberg were really talking about. The book discusses quarks and leptons, antiparticles and Feynman diagrams, curved space-time, the Big Bang and the expanding Universe. Suitable for undergraduate students in non-science as well as science subjects, it uses problems and worked examples to help readers develop an understanding of what recent advances in physics actually mean"--</subfield>
                     *     <subfield code="c">Provided by publisher.</subfield>
                     * </datafield>
                     */
                    $this->summary = [
                        'assigning_source' => $node->text('marc:subfield[@code="c"]'),
                        'text' => $node->text('marc:subfield[@code="a"]'),
                    ];
                    break;

                // 580 : Complex Linking Note (R)
                case 580:

                    if ($data->has('marc:datafield[@tag="780"]')) {
                        $preceding['note'] = $node->text('marc:subfield[@code="a"]');
                    } elseif ($data->has('marc:datafield[@tag="785"]')) {
                        $succeeding['note'] = $node->text('marc:subfield[@code="a"]');
                    } elseif ($data->has('marc:datafield[@tag="773"]')) {
                        $part_of['note'] = $node->text('marc:subfield[@code="a"]');
                    }
                    break;

                case 600:
                    $tmp = $this->parseSubjectAddedEntry($node);

                    $name = $node->text('marc:subfield[@code="a"]');
                    $qualifiers = array();
                    $titles = trim($node->text('marc:subfield[@code="c"]'), ':');
                    if (!empty($titles)) {
                        $qualifiers[] = trim($titles, '(),.');
                    }
                    $dates = $node->text('marc:subfield[@code="d"]');
                    if (!empty($dates)) {
                        $qualifiers[] = trim($dates, '(),.');
                    }

                    /**
                     * - To concat or not concat… not sure, but in principle
                     * the $a $c $d $q values should be the same on all
                     * records, so for filtering purposes it doesn't seem
                     * necessary to have them separated. For display
                     * purposes, it can be useful of course. But then again,
                     * the parts should be available from the authority
                     * register..
                     * - Another question is whether we should do some
                     * normalizing to try aligning records with different
                     * punctuation standards (US vs UK)
                     */
                    if (count($qualifiers) != 0) {
                        $name = "$name (" . implode(', ', $qualifiers) . ')';
                    }
                    $tmp['term'] = $name . $tmp['term'];
                    $tmp['type'] = 'person';

                    array_push($subjects, $tmp);
                    break;

                case 610:
                    $tmp = $this->parseSubjectAddedEntry($node);

                    $name = trim($node->text('marc:subfield[@code="a"]'), ',');
                    foreach ($node->all('marc:subfield[@code="b"]') as $subunit) {
                        $name .= self::$subfieldSeparator . trim($subunit, ',');
                    }
                    $tmp['type'] = 'corporation';
                    $tmp['term'] = $name . $tmp['term'];
                    array_push($subjects, $tmp);
                    break;

                case 611:
                    $tmp = $this->parseSubjectAddedEntry($node);
                    $dates = $node->text('marc:subfield[@code="d"]');
                    if (!empty($dates)) {
                        $tmp['time'] = trim($dates, ' :,.()');
                    }
                    $location = $node->text('marc:subfield[@code="c"]');
                    if (!empty($location)) {
                        $tmp['place'] = trim($location, ' :,.()');
                    }
                    $misc = $node->text('marc:subfield[@code="g"]');
                    if (!empty($misc)) {
                        $tmp['misc'] = trim($misc, ' :,.()');
                    }
                    $number = $node->text('marc:subfield[@code="n"]');
                    if (!empty($number)) {
                        $tmp['number'] = trim($number, ' :,.()');
                    }

                    $name = trim($node->text('marc:subfield[@code="a"]'), ',');
                    $tmp['type'] = 'meeting';
                    $tmp['term'] = $name . $tmp['term'];
                    array_push($subjects, $tmp);
                    break;

                case 648:
                    $tmp = $this->parseSubjectAddedEntry($node);

                    $emne = $node->text('marc:subfield[@code="a"]');
                    $tmp['type'] = 'chronologic';
                    $tmp['term'] = trim($emne, '.') . $tmp['term'];

                    array_push($subjects, $tmp);
                    break;

                case 650:
                    $tmp = $this->parseSubjectAddedEntry($node);

                    $emne = $node->text('marc:subfield[@code="a"]');
                    $tmp['term'] = trim($emne, '.') . $tmp['term'];
                    $tmp['type'] = 'topic';

                    array_push($subjects, $tmp);
                    break;

                case 651:
                    $tmp = $this->parseSubjectAddedEntry($node);

                    $emne = $node->text('marc:subfield[@code="a"]');
                    $tmp['type'] = 'geographic';
                    $tmp['term'] = trim($emne, '.') . $tmp['term'];

                    array_push($subjects, $tmp);
                    break;

                case 653:
                    $tmp = array('type' => null, 'vocabulary' => null);

                    $ind2 = $node->attr('ind2');
                    $types = [
                        '0' => 'topic',
                        '1' => 'person',
                        '2' => 'corporate',
                        '3' => 'meeting',
                        '4' => 'chronologic',
                        '5' => 'geographic',
                        '6' => 'genre',
                    ];

                    if (isset($types[$ind2])) {
                        $tmp['type'] = $types[$ind2];
                    }

                    foreach ($node->all('marc:subfield[@code="a"]') as $emne) {

                        $tmp['term'] = trim($emne, '.');

                        if ($tmp['type'] == 'genre') {
                            unset($tmp['type']);
                            array_push($genres, $tmp);
                        } else {
                            array_push($subjects, $tmp);
                        }
                    }

                    break;

                case 655:

                    $tmp = $this->parseSubjectAddedEntry($node);
                    $tmp['term'] = trim($node->text('marc:subfield[@code="a"]'), '.') . $tmp['term'];

                    array_push($genres, $tmp);
                    break;

                case 700:
                    $author = [ 'name' => $node->text('marc:subfield[@code="a"]') ];
                    $author['normalizedName'] = $author['name'];
                    $spl = explode(', ', $author['name']);
                    if (count($spl) == 2) {
                        $author['name'] = $spl[1] . ' ' . $spl[0];
                    }

                    $this->parseRelator($node, $author, 'added');
                    $this->parseAuthority($node->text('marc:subfield[@code="0"]'), $author);

                    $dates = trim($node->text('marc:subfield[@code="d"]'), '-');
                    if (!empty($dates)) {
                        $author['dates'] = $dates;
                    }

                    $creators[] = $author;
                    break;

                case 710:
                    $author = array(
                        'name' => $node->text('marc:subfield[@code="a"]'),
                    );
                    $author['normalizedName'] = $author['name'];

                    $this->parseRelator($node, $author, 'added_corporate');
                    $this->parseAuthority($node->text('marc:subfield[@code="0"]'), $author);

                    $creators[] = $author;
                    break;

                // 773 : Host Item Entry (R)
                // See also: 580
                case 773:
                    $part_of = isset($part_of) ? $part_of : array();
                    $part_of['relationship'] = $node->text('marc:subfield[@code="i"]');
                    $part_of['title'] = $node->text('marc:subfield[@code="t"]');
                    $part_of['issn'] = $node->text('marc:subfield[@code="x"]') ?: null;
                    $part_of['isbn'] = $node->text('marc:subfield[@code="z"]') ?: null;
                    $part_of['volume'] = $node->text('marc:subfield[@code="v"]') ?: null;
                    $this->parseAuthority($node->text('marc:subfield[@code="w"]'), $part_of);

                    break;

                /**
                 * 776 : Additional Physical Form Entry (R)
                 * <marc:datafield tag="776" ind1="0" ind2=" ">
                 *    <marc:subfield code="z">9781107602175</marc:subfield>
                 *     <marc:subfield code="w">(NO-TrBIB)132191512</marc:subfield>
                 * </marc:datafield>
                 */
                case 776:
                    $other_form = $this->parseRelationship($node);
                    break;

                /**
                 * 780 : Preceding Entry (R)
                 * Information concerning the immediate predecessor of the target item
                 */
                case 780:
                    /**
                     * <marc:datafield tag="780" ind1="0" ind2="0">
                     *     <marc:subfield code="w">(NO-TrBIB)920713874</marc:subfield>
                     *     <marc:subfield code="g">nr 80(1961)</marc:subfield>
                     * </marc:datafield>
                     */

                    if (!isset($preceding['items'])) {
                        $preceding['items'] = array();
                    }
                    $preceding['items'][] = $this->parseRelationship($node);

                    $ind2 = $node->attr('ind2');
                    $relationship_types = [
                        '0' => 'Continues',
                        '1' => 'Continues in part',
                        '2' => 'Supersedes',
                        '3' => 'Supersedes in part',
                        '4' => 'Formed by the union of',  // ... and ...',
                        '5' => 'Absorbed',
                        '6' => 'Absorbed in part',
                        '7' => 'Separated from',
                    ];

                    if (isset($relationship_types[$ind2])) {
                        $preceding['relationship_type'] = $relationship_types[$ind2];
                    }

                    break;

                /**
                 * 785 : Succeeding Entry (R)
                 * Information concerning the immediate successor to the target item
                 */
                case 785:
                    /**
                     * <marc:datafield tag="785" ind1="0" ind2="0">
                     *    <marc:subfield code="w">(NO-TrBIB)920713874</marc:subfield>
                     *     <marc:subfield code="g">nr 80(1961)</marc:subfield>
                     * </marc:datafield>
                     */
                    if (!isset($succeeding['items'])) {
                        $succeeding['items'] = array();
                    }

                    $succeeding['items'][] = $this->parseRelationship($node);

                    $ind2 = $node->attr('ind2');
                    $relationship_types = [
                        '0' => 'Continued by',
                        '1' => 'Continued in part by',
                        '2' => 'Superseded by',
                        '3' => 'Superseded in part by',
                        '4' => 'Absorbed by',
                        '5' => 'Absorbed in part by',
                        '6' => 'Split into',  // ... and ...',
                        '7' => 'Continued by', // more precisely 'Merged with ... to form ...', but we simplify slightly
                        '8' => 'Changed back to',
                    ];

                    if ($ind2 == '7') {
                        /**
                         * The first elements are the documents that go into the merge,
                         * we are not interested in those due to our slight simplification.
                         */
                        $succeeding['items'] = array(array_pop($succeeding['items']));
                    }

                    if (isset($relationship_types[$ind2])) {
                        $succeeding['relationship_type'] = $relationship_types[$ind2];
                    }
                    break;

                // 830 : Series Added Entry – Uniform Title (R)
                case 830:
                    $serie = [
                        'title' => $node->text('marc:subfield[@code="a"]'),
                        'id' => preg_replace('/\(NO-TrBIB\)/', '', $node->text('marc:subfield[@code="w"]')) ?: null,
                        'volume' => $node->text('marc:subfield[@code="v"]') ?: null,
                    ];
                    $series[] = $serie;
                    break;

                case 856:
                case 956:
                    /**
                     * MARC 21 uses field 856 for electronic "links", where you can have URLs for example covers images and/or blurbs.
                     * 956 ?
                     * <marc:datafield tag="856" ind1="4" ind2="2">
                     *    <marc:subfield code="3">Beskrivelse fra forlaget (kort)</marc:subfield>
                     *     <marc:subfield code="u">http://content.bibsys.no/content/?type=descr_publ_brief&amp;isbn=0521176832</marc:subfield>
                     * </marc:datafield>
                     * <marc:datafield tag="956" ind1="4" ind2="2">
                     *     <marc:subfield code="3">Omslagsbilde</marc:subfield>
                     *     <marc:subfield code="u">http://innhold.bibsys.no/bilde/forside/?size=mini&amp;id=9780521176835.jpg</marc:subfield>
                     *     <marc:subfield code="q">image/jpeg</marc:subfield>
                     * </marc:datafield>
                     */
                    $description = $node->text('marc:subfield[@code="3"]');

                    if (in_array($description, array('Cover image', 'Omslagsbilde'))) {

                        $this->cover_image = $node->text('marc:subfield[@code="u"]');

                        /** Silly hack to get larger images from Bibsys: */
                        $this->cover_image = str_replace('mini', 'stor', $this->cover_image);
                        $this->cover_image = str_replace('LITE', 'STOR', $this->cover_image);
                    }

                    if (in_array($description, array('Beskrivelse fra forlaget (kort)', 'Beskrivelse fra forlaget (lang)'))) {
                        $this->description = $node->text('marc:subfield[@code="u"]');
                    }
                    break;

                /**
                 * 991 Kriterium für Sekundärsortierung (R) ???
                 * @ref http://ead.nb.admin.ch/web/marc21/dmarcb991.pdf
                 * Hvor i BIBSYSMARC kommer dette fra?
                 */
                case 991:

                    // Multi-volume work (flerbindsverk), parts linked through 773 w
                    if ($node->text('marc:subfield[@code="a"]') == 'volumes') {
                        $this->is_multivolume = true;
                    }

                    // Series (serier), parts linked through 830 w
                    if ($node->text('marc:subfield[@code="a"]') == 'parts') {
                        $this->is_series = true;
                    }

                    break;
            }
        }

        if (!empty($preceding)) {
            $this->preceding = $preceding;
        }
        if (!empty($succeeding)) {
            $this->succeeding = $succeeding;
        }
        if (count($part_of)) {
            $this->part_of = $part_of;
        }
        if (!empty($other_form)) {
            $this->other_form = $other_form;
        }

        $this->alternativeTitles = array_unique($alternativeTitles);
        $this->isbns = $isbns;
        $this->issns = $issns;
        $this->series = $series;
        $this->creators = $creators;
        $this->meetings = $meetings;
        $this->subjects = $subjects;
        $this->genres = $genres;
        $this->classifications = $classifications;
        $this->notes = $notes;
    }

    /**
     * @param string $x1
     * @param string $x2
     * @param string $default
     *
     * @return string
     */
    public function getMaterialSubtypeFrom007($x1, $x2, $default = 'Unknown')
    {
        $f007values = [
            'a' => [
                'd' => 'Atlas',
                'g' => 'Diagram',
                'j' => 'Map',
                'k' => 'Profile',
                'q' => 'Model',
                'r' => 'Remote-sensing image',
                '_' => 'Map',
            ],
            'c' => [
                'a' => 'Tape cartridge',
                'b' => 'Chip cartridge',
                'c' => 'Computer optical disc cartridge',
                'd' => 'Computer disc, type unspecified',
                'e' => 'Computer disc cartridge, type unspecified',
                'f' => 'Tape cassette',
                'h' => 'Tape reel',
                'j' => 'Magnetic disk',
                'k' => 'Computer card',
                'm' => 'Magneto-optical disc',
                'o' => 'CD-ROM',                // Optical disc
                'r' => 'Remote resource',    // n Nettdokumenter
            ],
            'f' => [
                'a' => 'Moon',         // in the Moon writing system
                'b' => 'Braille',      // in the Braille writing system
                'c' => 'Combination ', // in a combination of two or more of the other defined types
                'd' => 'No writing system',
            ],
            'h' => [
                'a' => 'Aperture card',
                'b' => 'Microfilm cartridge',
                'c' => 'Microfilm cassette',
                'd' => 'Microfilm reel',
                'e' => 'Microfiche',
                'f' => 'Microfiche cassette',
                'g' => 'Microopaque',
                'h' => 'Microfilm slip',
                'j' => 'Microfilm roll',
                'u' => 'Microform', // Unspecified
                'z' => 'Microform', // Other
                '|' => 'Microform', // No attempt to code
            ],
            'o' => [
                'u' => 'Kit',
                '|' => 'Kit',
            ],
            's' => [
                'd' => 'Music CD',             // v CD-er
                'e' => 'Cylinder',
                'g' => 'Sound cartridge',
                'i' => 'Sound-track film',
                'q' => 'Roll',
                's' => 'Sound cassette',
                't' => 'Sound-tape reel',
                'u' => 'Unspecified',
                'w' => 'Wire recording',
            ],
            'v' => [
                'c' => 'Videocartridge',
                'd' => 'Videodisc',           // w DVD-er
                'f' => 'Videocassette',
                'r' => 'Videoreel',
            ],
        ];

        if (isset($f007values[$x1]) && isset($f007values[$x1][$x2])) {
            return $f007values[$x1][$x2];
        }

        return $default;
    }

    /**
     * @param \Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement $data
     */
    protected function parseMaterial(QuiteSimpleXmlElement $data)
    {
        /** LDR/06 */
        $recordTypes = [
            'a' => 'Language material', /** Texte imprimé */
            'b' => 'Manuscript text', /** Texte manuscrit */
            'c' => 'Notated music', /** Partition musicale imprimée */
            'd' => 'Manuscript notated music', /** Partition musicale manuscrite */
            'e' => 'Cartographic material', /** Document cartographique imprimé */
            'f' => 'Manuscript cartographic material', /** Document cartographique manuscrit */
            'g' => 'Projected medium', /** Document projeté ou vidéo (films, bandes films, diapositives, transparents, enregistrements vidéo) */
            'i' => 'Nonmusical sound recording',
            'j' => 'Musical sound recording',
            'k' => 'Two-dimensional nonprojectable graphic',
            'm' => 'Computer file',
            'o' => 'Kit',
            'p' => 'Mixed materials',
            'r' => 'Three-dimensional artifact or naturally occurring object',
            't' => 'Manuscript language material',
        ];

        /** LDR/07 */
        $bibliographicLevels = [
            'a' => 'Monographic component part', /** Analytique (partie composante) */
            'b' => 'Serial component part',
            'c' => 'Collection', /** Recueil factice */
            'd' => 'Subunit',
            'i' => 'Integrating resource', /** Ressource intégratrice */
            'm' => 'Monograph/Item', /** Monographie */
            's' => 'Serial', /** Publication en série */
        ];

        /** 007/00 (Category of material) */
        $materialCategories = [
            'a' => 'Map',
            'c' => 'Electronic resource',
            'd' => 'Globe',
            'f' => 'Tactile material',
            'g' => 'Projected graphic',
            'h' => 'Microform',
            'k' => 'Nonprojected graphic',
            'm' => 'Motion picture',
            'o' => 'Kit',
            'q' => 'Notated music',
            'r' => 'Remote-sensing image',
            's' => 'Sound recording',
            't' => 'Text',                 // p Trykt materiale
            'v' => 'Videorecording',
            'z' => 'Unspecified',
        ];

        /** 008/24-27 - Nature of contents (006/07-10) */
        $natureOfContents = [
            'a' => 'Abstract',
            'b' => 'Bibliography',
            'c' => 'Catalog',
            'd' => 'Dictionary',
            'e' => 'Encyclopedia',
            'f' => 'Handbook',
            'g' => 'Legal article',
            'i' => 'Index',
            'j' => 'Patent document',
            'k' => 'Discography',
            'l' => 'Legislation',
            'm' => 'Thesis',
            'n' => 'Surveys of literature in a subject area',
            'o' => 'Review',
            'p' => 'Programmed text',
            'q' => 'Filmography',
            'r' => 'Directory',
            's' => 'Statistics',
            't' => 'Technical report',
            'u' => 'Standards/specification',
            'v' => 'Legal cases and case notes',
            'w' => 'Law reports and digests',
            'y' => 'Yearbook',
            'z' => 'Treaty',
            '2' => 'Offprint',
            '5' => 'Calendar',
            '6' => 'Comics/graphic novel',
        ];

        $videoFormats = [
            'a' => 'Beta (1/2 in., videocassette)',
            'b' => 'VHS (1/2 in., videocassette)',
            'c' => 'U-matic (3/4 in., videocasstte)',
            'd' => 'EIAJ (1/2 in., reel)',
            'e' => 'Type C (1 in., reel)',
            'f' => 'Quadruplex (1 in. or 2 in., reel)',
            'g' => 'Laserdisc',
            'h' => 'CED (Capacitance Electronic Disc) videodisc',
            'i' => 'Betacam (1/2 in., videocassette)',
            'j' => 'Betacam SP (1/2 in., videocassette)',
            'k' => 'Super-VHS (1/2 in., videocassette)',
            'm' => 'M-II (1/2 in., videocassette)',
            'o' => 'D-2 (3/4 in., videocassette)',
            'p' => '8 mm.',
            'q' => 'Hi-8 mm.',
            's' => 'Blu-ray',
            'u' => 'Unknown',
            'v' => 'DVD',
        ];

        $ldr = str_split($data->text('marc:leader'));
        $f007 = str_split($data->text('marc:controlfield[@tag="007"]'));
        $f008 = str_split($data->text('marc:controlfield[@tag="008"]'));

        $material = 'Unknown';
        $this->material = $material;
        $this->electronic = false;

        $this->debug = [
            'ldr06' => array_get($ldr, 6),
            'ldr07' => array_get($ldr, 7),
            'f7_01' => array_get($f007, 0),
            'f7_02' => array_get($f007, 1),
        ];

        if (count($ldr) < 8) { return; }

        //if (count($f007) < 2) { return; }

        switch ($ldr[6]) {

            case 'a':

                if (in_array($ldr[7], ['a', 'c', 'd', 'm'])) {
                    $material = 'Book';
                }

                if (in_array($ldr[7], ['b', 'i', 's'])) {
                    $material = 'Series';
                }

                break;

            case 't':
                $material = 'Book';
                break;

            case 'c':
            case 'd':
            case 'i':
            case 'j':
                $material = 'Music';
                break;

            case 'e':
            case 'f':
                $material = 'Map';
                break;

            case 'g':
            case 'k':
            case 'o':
            case 'r':
                $material = 'Visual';
                break;

            case 'm':
                $material = 'File';
                /** used for computer software, numeric data, not for e-books or e-journals! */
                break;

            case 'p':
                $material = 'Mixed';
                break;

        }

        $online = ($f007[0] == 'c' && $f007[1] == 'r');

        if ($material == 'File') {

            if(array_keys($f007, true)) {
                $material = $this->getMaterialSubtypeFrom007($f007[0], $f007[1], $material);
            }
        }
        elseif ($material == 'Visual') {

            if(array_keys($f007, true)) {
                $material = $this->getMaterialSubtypeFrom007($f007[0], $f007[1], $material);
            }

            if (isset($f007[4]) && isset($videoFormats[$f007[4]])) {
                $material = $videoFormats[$f007[4]]; /** DVD, Blu-ray */
            }

        }
        elseif ($material == 'Music') {

            if ($f007[0] == 't') {
                $material = 'Sheet music';
            }
            else {

                if(array_keys($f007, true)) {
                    $material = $this->getMaterialSubtypeFrom007($f007[0], $f007[1], $material);
                }

                if (array_get($ldr, 7) == 'a') {
                    $material .= ' track';
                }
            }

        }
        elseif ($material == 'Series') {
            switch ($f008[21]) {
                case 'm':
                    $material = 'Series';  /** Monographic series (merk: skiller ikke mellom 'flerbindsverk' og 'serieinnførsel') */
                    break;
                case 'n':
                    $material = 'Newspaper';
                    if ($f007[0] == 'h') {
                        $material .= ' on microform';
                    }
                    break;
                case 'p':
                    $material = 'Periodical';
                    break;
            }
            if (array_get($ldr, 7) == 'a') {

                $material = 'Article';
            }
        }
        elseif ($material == 'Book') {
            if (isset($f008[24]) and isset($natureOfContents[$f008[24]])) {
                // Slight simplification
                $material = $natureOfContents[$f008[24]];
            }

            if (array_get($ldr, 7) == 'a') {
                $material = 'Article';
                // or Article/Chapter ?
            }
        }

        $this->material = $material;
        $this->electronic = $online;
    }

    protected function parseDataGeneral(QuiteSimpleXmlElement $node)
    {
        $codeDateofpublication = [
            'a' => 'périodique en cours',
            'b' => 'périodique mort',
            'd' => 'monographie complète à la publication, ou publiée dans les limites d’une année civile',
            'e' =>'reproduction d’un document',
            'f' => 'monographie, date de publication incertaine',
            'g' => 'monographie dont la publication s’étend sur plus d’un an',
            'u' => 'date de publication inconnue'
        ];
        $destinatairePublic = [
            '#' => 'Non précisé',
            'a' => 'Jeunesse en général',
            'c' => 'Scolaire 5-10 ans',
            'd' => 'Enfant, 9-14 ans',
            'e' => 'Jeune adulte, 14-20 ans',
            'k' => 'Adulte, haut niveau',
            'm' => 'Adulte, grand public',
            'u' => 'Inconnu'
        ];
        $officiellePublications = [
            'f' => 'Publication officielle intergouvernementale',
            'h' => 'Publication officielle de niveau indéterminé',
            'y' => 'Il ne s\'agit pas d\'une publication officielle',
            'z' => 'Autre instance officielle'
        ];
        $Langue = [
            //
        ];

        /**
         * Ecriture du titre
         */
        $writingTitle = [
            'ba' => 'Latin',
            'ca' => 'Cyrillique',
            'da' => 'Japonais - alphabet non précisé',
            'dc'  => 'Japonais - Kana',
            'ea' => 'Chinois',
            'fa' => 'Arabe',
            'ga' => 'Grec',
            'ha' => 'Hébreu',
            'ia' => 'Thaï',
            'ja' => 'Devanagari',
            'ka' => 'Coréen',
            'la' => 'Tamoul',
            'ma' => 'Géorgien',
            'mb' => 'Arménien',
            'zz' => 'Autres',
            '||' => 'écritures multiples'
        ];

        /**
         * TODO: Change the autogenerated stub
         */
        $date =  preg_replace('/^.*?([0-9]{8}).*$/', '\1', $node->first('marc:subfield[@code="a"]'));
        $dates = $this->parseDateTime($date);
        $codePub = preg_replace('/^.*?([a-z]{1}).*$/', '\1', $node->text('marc:subfield[@code="a"]'));
        $firstPub = preg_replace('/^.*?([0-9]{8}).([0-9]{4}).*$/', '\2', $node->text('marc:subfield[@code="a"]'));
        dd($date, $dates, $codePub, $firstPub);

    }

    public function addClassification($node, &$classifications, $fields, $system = null, $edition = null, $assigner = null)
    {
        $cl = ['system' => $system, 'edition' => $edition, 'number' => null, 'assigner' => $assigner];

        foreach ($fields as $key => $val) {

            $t = $node->text('marc:subfield[@code="' . $key . '"]');

            if (!is_array($val)) {
                $val = array($val);
            }

            if (count($val) > 2) {
                $t = preg_replace('/' . $val[1] . '/', $val[2], $t);
            }

            if (!empty($t)) {
                $cl[$val[0]] = $t;
            }
        }

        if (is_null($cl['system'])) {
            return;
            // Invalid value in $a, should we trigger some event to allow logging?
        }

        if (is_null($cl['number'])) {
            return;
            // Invalid value in $a, should we trigger some event to allow logging?
        }

        $classifications[] = $cl;
    }

    /**
     * Parses common elements in subject added entry fields 600-655.
     *
     * @param \Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement $node
     *
     * @return array
     */
    public function parseSubjectAddedEntry(QuiteSimpleXmlElement &$node)
    {
        $out = ['term' => '', 'vocabulary' => null];
        $vocabularies = array(
            '0' => 'lcsh',
            '1' => 'lccsh', // LC subject headings for children's literature
            '2' => 'mesh', // Medical Subject Headings
            '3' => 'atg', // National Agricultural Library subject authority file (?)
            // 4 : unknown
            '5' => 'cash', // Canadian Subject Headings
            '6' => 'rvm', // Répertoire de vedettes-matière
            // 7: Source specified in subfield $2
        );

        $ind2 = $node->attr('ind2');

        $id = $node->text('marc:subfield[@code="0"]');
        $out['id'] = empty($id) ? null : $id;

        if (isset($vocabularies[$ind2])) {

            $out['vocabulary'] = $vocabularies[$ind2];

        } elseif ($ind2 == '7') {

            $vocab = $node->text('marc:subfield[@code="2"]');

            if (!empty($vocab)) {

                $out['vocabulary'] = $vocab;
            }

        } elseif ($ind2 == '4') {

            $this->parseAuthority($node->text('marc:subfield[@code="0"]'), $out);
        }

        $out['parts'] = [];

        $subdivtypes = [
            'v' => 'form',
            'x' => 'general',
            'y' => 'chronologic',
            'z' => 'geographic',
        ];

        foreach ($node->all('marc:subfield') as $subdiv) {

            $code = $subdiv->attr('code');

            if (in_array($code, array_keys($subdivtypes))) {
                $subdiv = trim($subdiv, '.');
                $out['parts'][] = ['value' => $subdiv, 'type' => $subdivtypes[$code] ];
                $out['term'] .= self::$subfieldSeparator . $subdiv;
            }
        }

        return $out;
    }

    /**
     * @param string $code
     * @return string Name of country
     * @see https://www.iso.org/obp/ui/#search
     */
    protected function getCountryByCode($code)
    {
        $country = '';
        if( $code == 'AF' ) $country = 'Afghanistan';
        if( $code == 'AX' ) $country = 'Aland Islands';
        if( $code == 'AL' ) $country = 'Albania';
        if( $code == 'DZ' ) $country = 'Algeria';
        if( $code == 'AS' ) $country = 'American Samoa';
        if( $code == 'AD' ) $country = 'Andorra';
        if( $code == 'AO' ) $country = 'Angola';
        if( $code == 'AI' ) $country = 'Anguilla';
        if( $code == 'AQ' ) $country = 'Antarctica';
        if( $code == 'AG' ) $country = 'Antigua and Barbuda';
        if( $code == 'AR' ) $country = 'Argentina';
        if( $code == 'AM' ) $country = 'Armenia';
        if( $code == 'AW' ) $country = 'Aruba';
        if( $code == 'AU' ) $country = 'Australia';
        if( $code == 'AT' ) $country = 'Austria';
        if( $code == 'AZ' ) $country = 'Azerbaijan';
        if( $code == 'BS' ) $country = 'Bahamas the';
        if( $code == 'BH' ) $country = 'Bahrain';
        if( $code == 'BD' ) $country = 'Bangladesh';
        if( $code == 'BB' ) $country = 'Barbados';
        if( $code == 'BY' ) $country = 'Belarus';
        if( $code == 'BE' ) $country = 'Belgium';
        if( $code == 'BZ' ) $country = 'Belize';
        if( $code == 'BJ' ) $country = 'Benin';
        if( $code == 'BM' ) $country = 'Bermuda';
        if( $code == 'BT' ) $country = 'Bhutan';
        if( $code == 'BO' ) $country = 'Bolivia';
        if( $code == 'BA' ) $country = 'Bosnia and Herzegovina';
        if( $code == 'BW' ) $country = 'Botswana';
        if( $code == 'BV' ) $country = 'Bouvet Island (Bouvetoya)';
        if( $code == 'BR' ) $country = 'Brazil';
        if( $code == 'IO' ) $country = 'British Indian Ocean Territory (Chagos Archipelago)';
        if( $code == 'VG' ) $country = 'British Virgin Islands';
        if( $code == 'BN' ) $country = 'Brunei Darussalam';
        if( $code == 'BG' ) $country = 'Bulgaria';
        if( $code == 'BF' ) $country = 'Burkina Faso';
        if( $code == 'BI' ) $country = 'Burundi';
        if( $code == 'KH' ) $country = 'Cambodia';
        if( $code == 'CM' ) $country = 'Cameroon';
        if( $code == 'CA' ) $country = 'Canada';
        if( $code == 'CV' ) $country = 'Cape Verde';
        if( $code == 'KY' ) $country = 'Cayman Islands';
        if( $code == 'CF' ) $country = 'Central African Republic';
        if( $code == 'TD' ) $country = 'Chad';
        if( $code == 'CL' ) $country = 'Chile';
        if( $code == 'CN' ) $country = 'China';
        if( $code == 'CX' ) $country = 'Christmas Island';
        if( $code == 'CC' ) $country = 'Cocos (Keeling) Islands';
        if( $code == 'CO' ) $country = 'Colombia';
        if( $code == 'KM' ) $country = 'Comoros the';
        if( $code == 'CD' ) $country = 'Congo';
        if( $code == 'CG' ) $country = 'Congo the';
        if( $code == 'CK' ) $country = 'Cook Islands';
        if( $code == 'CR' ) $country = 'Costa Rica';
        if( $code == 'CI' ) $country = 'Cote d\'Ivoire';
        if( $code == 'HR' ) $country = 'Croatia';
        if( $code == 'CU' ) $country = 'Cuba';
        if( $code == 'CY' ) $country = 'Cyprus';
        if( $code == 'CZ' ) $country = 'Czech Republic';
        if( $code == 'DK' ) $country = 'Denmark';
        if( $code == 'DJ' ) $country = 'Djibouti';
        if( $code == 'DM' ) $country = 'Dominica';
        if( $code == 'DO' ) $country = 'Dominican Republic';
        if( $code == 'EC' ) $country = 'Ecuador';
        if( $code == 'EG' ) $country = 'Egypt';
        if( $code == 'SV' ) $country = 'El Salvador';
        if( $code == 'GQ' ) $country = 'Equatorial Guinea';
        if( $code == 'ER' ) $country = 'Eritrea';
        if( $code == 'EE' ) $country = 'Estonia';
        if( $code == 'ET' ) $country = 'Ethiopia';
        if( $code == 'FO' ) $country = 'Faroe Islands';
        if( $code == 'FK' ) $country = 'Falkland Islands (Malvinas)';
        if( $code == 'FJ' ) $country = 'Fiji the Fiji Islands';
        if( $code == 'FI' ) $country = 'Finland';
        if( $code == 'FR' ) $country = 'France, French Republic';
        if( $code == 'GF' ) $country = 'French Guiana';
        if( $code == 'PF' ) $country = 'French Polynesia';
        if( $code == 'TF' ) $country = 'French Southern Territories';
        if( $code == 'GA' ) $country = 'Gabon';
        if( $code == 'GM' ) $country = 'Gambia the';
        if( $code == 'GE' ) $country = 'Georgia';
        if( $code == 'DE' ) $country = 'Germany';
        if( $code == 'GH' ) $country = 'Ghana';
        if( $code == 'GI' ) $country = 'Gibraltar';
        if( $code == 'GR' ) $country = 'Greece';
        if( $code == 'GL' ) $country = 'Greenland';
        if( $code == 'GD' ) $country = 'Grenada';
        if( $code == 'GP' ) $country = 'Guadeloupe';
        if( $code == 'GU' ) $country = 'Guam';
        if( $code == 'GT' ) $country = 'Guatemala';
        if( $code == 'GG' ) $country = 'Guernsey';
        if( $code == 'GN' ) $country = 'Guinea';
        if( $code == 'GW' ) $country = 'Guinea-Bissau';
        if( $code == 'GY' ) $country = 'Guyana';
        if( $code == 'HT' ) $country = 'Haiti';
        if( $code == 'HM' ) $country = 'Heard Island and McDonald Islands';
        if( $code == 'VA' ) $country = 'Holy See (Vatican City State)';
        if( $code == 'HN' ) $country = 'Honduras';
        if( $code == 'HK' ) $country = 'Hong Kong';
        if( $code == 'HU' ) $country = 'Hungary';
        if( $code == 'IS' ) $country = 'Iceland';
        if( $code == 'IN' ) $country = 'India';
        if( $code == 'ID' ) $country = 'Indonesia';
        if( $code == 'IR' ) $country = 'Iran';
        if( $code == 'IQ' ) $country = 'Iraq';
        if( $code == 'IE' ) $country = 'Ireland';
        if( $code == 'IM' ) $country = 'Isle of Man';
        if( $code == 'IL' ) $country = 'Israel';
        if( $code == 'IT' ) $country = 'Italy';
        if( $code == 'JM' ) $country = 'Jamaica';
        if( $code == 'JP' ) $country = 'Japan';
        if( $code == 'JE' ) $country = 'Jersey';
        if( $code == 'JO' ) $country = 'Jordan';
        if( $code == 'KZ' ) $country = 'Kazakhstan';
        if( $code == 'KE' ) $country = 'Kenya';
        if( $code == 'KI' ) $country = 'Kiribati';
        if( $code == 'KP' ) $country = 'Korea';
        if( $code == 'KR' ) $country = 'Korea';
        if( $code == 'KW' ) $country = 'Kuwait';
        if( $code == 'KG' ) $country = 'Kyrgyz Republic';
        if( $code == 'LA' ) $country = 'Lao';
        if( $code == 'LV' ) $country = 'Latvia';
        if( $code == 'LB' ) $country = 'Lebanon';
        if( $code == 'LS' ) $country = 'Lesotho';
        if( $code == 'LR' ) $country = 'Liberia';
        if( $code == 'LY' ) $country = 'Libyan Arab Jamahiriya';
        if( $code == 'LI' ) $country = 'Liechtenstein';
        if( $code == 'LT' ) $country = 'Lithuania';
        if( $code == 'LU' ) $country = 'Luxembourg';
        if( $code == 'MO' ) $country = 'Macao';
        if( $code == 'MK' ) $country = 'Macedonia';
        if( $code == 'MG' ) $country = 'Madagascar';
        if( $code == 'MW' ) $country = 'Malawi';
        if( $code == 'MY' ) $country = 'Malaysia';
        if( $code == 'MV' ) $country = 'Maldives';
        if( $code == 'ML' ) $country = 'Mali';
        if( $code == 'MT' ) $country = 'Malta';
        if( $code == 'MH' ) $country = 'Marshall Islands';
        if( $code == 'MQ' ) $country = 'Martinique';
        if( $code == 'MR' ) $country = 'Mauritania';
        if( $code == 'MU' ) $country = 'Mauritius';
        if( $code == 'YT' ) $country = 'Mayotte';
        if( $code == 'MX' ) $country = 'Mexico';
        if( $code == 'FM' ) $country = 'Micronesia';
        if( $code == 'MD' ) $country = 'Moldova';
        if( $code == 'MC' ) $country = 'Monaco';
        if( $code == 'MN' ) $country = 'Mongolia';
        if( $code == 'ME' ) $country = 'Montenegro';
        if( $code == 'MS' ) $country = 'Montserrat';
        if( $code == 'MA' ) $country = 'Morocco';
        if( $code == 'MZ' ) $country = 'Mozambique';
        if( $code == 'MM' ) $country = 'Myanmar';
        if( $code == 'NA' ) $country = 'Namibia';
        if( $code == 'NR' ) $country = 'Nauru';
        if( $code == 'NP' ) $country = 'Nepal';
        if( $code == 'AN' ) $country = 'Netherlands Antilles';
        if( $code == 'NL' ) $country = 'Netherlands the';
        if( $code == 'NC' ) $country = 'New Caledonia';
        if( $code == 'NZ' ) $country = 'New Zealand';
        if( $code == 'NI' ) $country = 'Nicaragua';
        if( $code == 'NE' ) $country = 'Niger';
        if( $code == 'NG' ) $country = 'Nigeria';
        if( $code == 'NU' ) $country = 'Niue';
        if( $code == 'NF' ) $country = 'Norfolk Island';
        if( $code == 'MP' ) $country = 'Northern Mariana Islands';
        if( $code == 'NO' ) $country = 'Norway';
        if( $code == 'OM' ) $country = 'Oman';
        if( $code == 'PK' ) $country = 'Pakistan';
        if( $code == 'PW' ) $country = 'Palau';
        if( $code == 'PS' ) $country = 'Palestinian Territory';
        if( $code == 'PA' ) $country = 'Panama';
        if( $code == 'PG' ) $country = 'Papua New Guinea';
        if( $code == 'PY' ) $country = 'Paraguay';
        if( $code == 'PE' ) $country = 'Peru';
        if( $code == 'PH' ) $country = 'Philippines';
        if( $code == 'PN' ) $country = 'Pitcairn Islands';
        if( $code == 'PL' ) $country = 'Poland';
        if( $code == 'PT' ) $country = 'Portugal, Portuguese Republic';
        if( $code == 'PR' ) $country = 'Puerto Rico';
        if( $code == 'QA' ) $country = 'Qatar';
        if( $code == 'RE' ) $country = 'Reunion';
        if( $code == 'RO' ) $country = 'Romania';
        if( $code == 'RU' ) $country = 'Russian Federation';
        if( $code == 'RW' ) $country = 'Rwanda';
        if( $code == 'BL' ) $country = 'Saint Barthelemy';
        if( $code == 'SH' ) $country = 'Saint Helena';
        if( $code == 'KN' ) $country = 'Saint Kitts and Nevis';
        if( $code == 'LC' ) $country = 'Saint Lucia';
        if( $code == 'MF' ) $country = 'Saint Martin';
        if( $code == 'PM' ) $country = 'Saint Pierre and Miquelon';
        if( $code == 'VC' ) $country = 'Saint Vincent and the Grenadines';
        if( $code == 'WS' ) $country = 'Samoa';
        if( $code == 'SM' ) $country = 'San Marino';
        if( $code == 'ST' ) $country = 'Sao Tome and Principe';
        if( $code == 'SA' ) $country = 'Saudi Arabia';
        if( $code == 'SN' ) $country = 'Senegal';
        if( $code == 'RS' ) $country = 'Serbia';
        if( $code == 'SC' ) $country = 'Seychelles';
        if( $code == 'SL' ) $country = 'Sierra Leone';
        if( $code == 'SG' ) $country = 'Singapore';
        if( $code == 'SK' ) $country = 'Slovakia (Slovak Republic)';
        if( $code == 'SI' ) $country = 'Slovenia';
        if( $code == 'SB' ) $country = 'Solomon Islands';
        if( $code == 'SO' ) $country = 'Somalia, Somali Republic';
        if( $code == 'ZA' ) $country = 'South Africa';
        if( $code == 'GS' ) $country = 'South Georgia and the South Sandwich Islands';
        if( $code == 'ES' ) $country = 'Spain';
        if( $code == 'LK' ) $country = 'Sri Lanka';
        if( $code == 'SD' ) $country = 'Sudan';
        if( $code == 'SR' ) $country = 'Suriname';
        if( $code == 'SJ' ) $country = 'Svalbard & Jan Mayen Islands';
        if( $code == 'SZ' ) $country = 'Swaziland';
        if( $code == 'SE' ) $country = 'Sweden';
        if( $code == 'CH' ) $country = 'Switzerland, Swiss Confederation';
        if( $code == 'SY' ) $country = 'Syrian Arab Republic';
        if( $code == 'TW' ) $country = 'Taiwan';
        if( $code == 'TJ' ) $country = 'Tajikistan';
        if( $code == 'TZ' ) $country = 'Tanzania';
        if( $code == 'TH' ) $country = 'Thailand';
        if( $code == 'TL' ) $country = 'Timor-Leste';
        if( $code == 'TG' ) $country = 'Togo';
        if( $code == 'TK' ) $country = 'Tokelau';
        if( $code == 'TO' ) $country = 'Tonga';
        if( $code == 'TT' ) $country = 'Trinidad and Tobago';
        if( $code == 'TN' ) $country = 'Tunisia';
        if( $code == 'TR' ) $country = 'Turkey';
        if( $code == 'TM' ) $country = 'Turkmenistan';
        if( $code == 'TC' ) $country = 'Turks and Caicos Islands';
        if( $code == 'TV' ) $country = 'Tuvalu';
        if( $code == 'UG' ) $country = 'Uganda';
        if( $code == 'UA' ) $country = 'Ukraine';
        if( $code == 'AE' ) $country = 'United Arab Emirates';
        if( $code == 'GB' ) $country = 'United Kingdom';
        if( $code == 'US' ) $country = 'United States of America';
        if( $code == 'UM' ) $country = 'United States Minor Outlying Islands';
        if( $code == 'VI' ) $country = 'United States Virgin Islands';
        if( $code == 'UY' ) $country = 'Uruguay, Eastern Republic of';
        if( $code == 'UZ' ) $country = 'Uzbekistan';
        if( $code == 'VU' ) $country = 'Vanuatu';
        if( $code == 'VE' ) $country = 'Venezuela';
        if( $code == 'VN' ) $country = 'Vietnam';
        if( $code == 'WF' ) $country = 'Wallis and Futuna';
        if( $code == 'EH' ) $country = 'Western Sahara';
        if( $code == 'YE' ) $country = 'Yemen';
        if( $code == 'ZM' ) $country = 'Zambia';
        if( $code == 'ZW' ) $country = 'Zimbabwe';
        if( $country == '') $country = $code;
        return $country;
    }

    /**
     * 0XX Bloc de l’identification
     * @see  http://www.bnf.fr/fr/professionnels/anx_formats/a.unimarc_manuel_format_bibliographique.html
     */
    protected function parseIdentification($code, QuiteSimpleXmlElement $node)
    {
        //
    }

    /**
     * 1XX Bloc des informations codées
     * @see  http://www.bnf.fr/fr/professionnels/anx_formats/a.unimarc_manuel_format_bibliographique.html
     */
    protected function parseInformations($code, QuiteSimpleXmlElement $node)
    {
        //
    }

    /**
     * 2XX Bloc des informations descriptives
     * @see  http://www.bnf.fr/fr/professionnels/anx_formats/a.unimarc_manuel_format_bibliographique.html
     */
    protected function parseDescriptives($code, QuiteSimpleXmlElement $node)
    {
        //
    }

    /**
     * 3XX Bloc des notes
     * @see  http://www.bnf.fr/fr/professionnels/anx_formats/a.unimarc_manuel_format_bibliographique.html
     */
    protected function parseNotes($code, QuiteSimpleXmlElement $node)
    {
        //
    }
}