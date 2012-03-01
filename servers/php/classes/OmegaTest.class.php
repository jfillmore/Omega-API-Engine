<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine

   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Contains various input validation tests for common input (e.g. hostname, e-mail address, file, etc). */
abstract class OmegaTest {
    const hostname_re = '/^([a-zA-Z0-9_-]+\.)*[a-zA-Z0-9-]+\.[a-zA-Z0-9\-]+$/';
    const ip4_address_re = '/^\d{1,3}(\.\d{1,3}){3}$/';
    const ip6_address_re = '/^(\:{0,2}[a-fA-F0-9]{1,4}\:{0,2}){3,39}$/';
    const email_address_re = '/^[a-zA-Z0-9+._-]+@[a-zA-Z0-9+._\-]+$/';
    const file_name_re = '/^[\'()a-zA-Z0-9@&%^*<>, _\.\-]+$/';
    const file_path_re = '/^[\'()\/a-zA-Z0-9@&%^*<>, _\.\-]+$/';
    const word_re = '/^[a-zA-Z0-9_-]+$/';
    const float2_re = '/^[0-9]*(\.[0-9]{1,2})?$/';
    const float3_re = '/^[0-9]*(\.[0-9]{1,3})?$/';

    /** Checks whether a number is an integer greater than or equal to zero.
        expects: num=number
        returns: boolean */
    static public function int_non_neg($num) {
        return preg_match('/^[0-9]+$/', $num);
    }

    /** Returns information about a country by name, 2 digit or 3 digit ISO 3166-1 alpha code.
        expects: country=string
        returns: object */
    static public function get_country($country) {
        $countries = OmegaTest::get_countries();
        foreach ($countries as $name => $data) {
            if (strtolower($name) === strtolower($country)
                || $data['alpha_2'] === strtoupper($country)
                || $data['alpha_3'] === strtoupper($country)
                ) {
                $data['name'] = $name;
                return $data;
            }
        }
        throw new Exception("Unrecognized country: $country.");
    }

    /** Returns information about the countries.
        returns: object */
    static public function get_countries() {
        // ISO-3166 data + calling codes
        return array(
            // English short name
            // Alpha-2 code
            // Alpha-3 code
            // Numeric code
            // ISO 3166-2 codes
            'Afghanistan' => array(
                'alpha_2' => 'AF',
                'alpha_3' => 'AFG',
                'num_code' => '004',
                'calling_code' => '63',
                'iso_code' => 'ISO 3166-2:AF'
            ),
            'Aland Islands' => array(
                'alpha_2' => 'AX',
                'alpha_3' => 'ALA',
                'num_code' => '248',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:AX'
            ),
            'Albania' => array(
                'alpha_2' => 'AL',
                'alpha_3' => 'ALB',
                'num_code' => '008',
                'calling_code' => '355',
                'iso_code' => 'ISO 3166-2:AL'
            ),
            'Algeria' => array(
                'alpha_2' => 'DZ',
                'alpha_3' => 'DZA',
                'num_code' => '012',
                'calling_code' => '213',
                'iso_code' => 'ISO 3166-2:DZ'
            ),
            'American Samoa' => array(
                'alpha_2' => 'AS',
                'alpha_3' => 'ASM',
                'num_code' => '016',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:AS'
            ),
            'Andorra' => array(
                'alpha_2' => 'AD',
                'alpha_3' => 'AND',
                'num_code' => '020',
                'calling_code' => '376',
                'iso_code' => 'ISO 3166-2:AD'
            ),
            'Angola' => array(
                'alpha_2' => 'AO',
                'alpha_3' => 'AGO',
                'num_code' => '024',
                'calling_code' => '244',
                'iso_code' => 'ISO 3166-2:AO'
            ),
            'Anguilla' => array(
                'alpha_2' => 'AI',
                'alpha_3' => 'AIA',
                'num_code' => '660',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:AI'
            ),
            'Antarctica' => array(
                'alpha_2' => 'AQ',
                'alpha_3' => 'ATA',
                'num_code' => '010',
                'calling_code' => '6721',
                'iso_code' => 'ISO 3166-2:AQ'
            ),
            'Antigua and Barbuda' => array(
                'alpha_2' => 'AG',
                'alpha_3' => 'ATG',
                'num_code' => '028',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:AG'
            ),
            'Argentina' => array(
                'alpha_2' => 'AR',
                'alpha_3' => 'ARG',
                'num_code' => '032',
                'calling_code' => '54',
                'iso_code' => 'ISO 3166-2:AR'
            ),
            'Armenia' => array(
                'alpha_2' => 'AM',
                'alpha_3' => 'ARM',
                'num_code' => '051',
                'calling_code' => '374',
                'iso_code' => 'ISO 3166-2:AM'
            ),
            'Aruba' => array(
                'alpha_2' => 'AW',
                'alpha_3' => 'ABW',
                'num_code' => '533',
                'calling_code' => '297',
                'iso_code' => 'ISO 3166-2:AW'
            ),
            'Australia' => array(
                'alpha_2' => 'AU',
                'alpha_3' => 'AUS',
                'num_code' => '036',
                'calling_code' => '61',
                'iso_code' => 'ISO 3166-2:AU'
            ),
            'Austria' => array(
                'alpha_2' => 'AT',
                'alpha_3' => 'AUT',
                'num_code' => '040',
                'calling_code' => '43',
                'iso_code' => 'ISO 3166-2:AT'
            ),
            'Azerbaijan' => array(
                'alpha_2' => 'AZ',
                'alpha_3' => 'AZE',
                'num_code' => '031',
                'calling_code' => '994',
                'iso_code' => 'ISO 3166-2:AZ'
            ),
            'Bahamas' => array(
                'alpha_2' => 'BS',
                'alpha_3' => 'BHS',
                'num_code' => '044',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:BS'
            ),
            'Bahrain' => array(
                'alpha_2' => 'BH',
                'alpha_3' => 'BHR',
                'num_code' => '048',
                'calling_code' => '973',
                'iso_code' => 'ISO 3166-2:BH'
            ),
            'Bangladesh' => array(
                'alpha_2' => 'BD',
                'alpha_3' => 'BGD',
                'num_code' => '050',
                'calling_code' => '880',
                'iso_code' => 'ISO 3166-2:BD'
            ),
            'Barbados' => array(
                'alpha_2' => 'BB',
                'alpha_3' => 'BRB',
                'num_code' => '052',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:BB'
            ),
            'Belarus' => array(
                'alpha_2' => 'BY',
                'alpha_3' => 'BLR',
                'num_code' => '112',
                'calling_code' => '375',
                'iso_code' => 'ISO 3166-2:BY'
            ),
            'Belgium' => array(
                'alpha_2' => 'BE',
                'alpha_3' => 'BEL',
                'num_code' => '056',
                'calling_code' => '32',
                'iso_code' => 'ISO 3166-2:BE'
            ),
            'Belize' => array(
                'alpha_2' => 'BZ',
                'alpha_3' => 'BLZ',
                'num_code' => '084',
                'calling_code' => '501',
                'iso_code' => 'ISO 3166-2:BZ'
            ),
            'Benin' => array(
                'alpha_2' => 'BJ',
                'alpha_3' => 'BEN',
                'num_code' => '204',
                'calling_code' => '229',
                'iso_code' => 'ISO 3166-2:BJ'
            ),
            'Bermuda' => array(
                'alpha_2' => 'BM',
                'alpha_3' => 'BMU',
                'num_code' => '060',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:BM'
            ),
            'Bhutan' => array(
                'alpha_2' => 'BT',
                'alpha_3' => 'BTN',
                'num_code' => '064',
                'calling_code' => '975',
                'iso_code' => 'ISO 3166-2:BT'
            ),
            'Bolivia, Plurinational State of' => array(
                'alpha_2' => 'BO',
                'alpha_3' => 'BOL',
                'num_code' => '068',
                'calling_code' => '591',
                'iso_code' => 'ISO 3166-2:BO'
            ),
            'Bonaire, Saint Eustatius and Saba' => array(
                'alpha_2' => 'BQ',
                'alpha_3' => 'BES',
                'num_code' => '535',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:BQ'
            ),
            'Bosnia and Herzegovina' => array(
                'alpha_2' => 'BA',
                'alpha_3' => 'BIH',
                'num_code' => '070',
                'calling_code' => '387',
                'iso_code' => 'ISO 3166-2:BA'
            ),
            'Botswana' => array(
                'alpha_2' => 'BW',
                'alpha_3' => 'BWA',
                'num_code' => '072',
                'calling_code' => '267',
                'iso_code' => 'ISO 3166-2:BW'
            ),
            'Bouvet Island' => array(
                'alpha_2' => 'BV',
                'alpha_3' => 'BVT',
                'num_code' => '074',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:BV'
            ),
            'Brazil' => array(
                'alpha_2' => 'BR',
                'alpha_3' => 'BRA',
                'num_code' => '076',
                'calling_code' => '55',
                'iso_code' => 'ISO 3166-2:BR'
            ),
            'British Indian Ocean Territory' => array(
                'alpha_2' => 'IO',
                'alpha_3' => 'IOT',
                'num_code' => '086',
                'calling_code' => '246',
                'iso_code' => 'ISO 3166-2:IO'
            ),
            'Brunei Darussalam' => array(
                'alpha_2' => 'BN',
                'alpha_3' => 'BRN',
                'num_code' => '096',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:BN'
            ),
            'Bulgaria' => array(
                'alpha_2' => 'BG',
                'alpha_3' => 'BGR',
                'num_code' => '100',
                'calling_code' => '359',
                'iso_code' => 'ISO 3166-2:BG'
            ),
            'Burkina Faso' => array(
                'alpha_2' => 'BF',
                'alpha_3' => 'BFA',
                'num_code' => '854',
                'calling_code' => '226',
                'iso_code' => 'ISO 3166-2:BF'
            ),
            'Burundi' => array(
                'alpha_2' => 'BI',
                'alpha_3' => 'BDI',
                'num_code' => '108',
                'calling_code' => '257',
                'iso_code' => 'ISO 3166-2:BI'
            ),
            'Cambodia' => array(
                'alpha_2' => 'KH',
                'alpha_3' => 'KHM',
                'num_code' => '116',
                'calling_code' => '855',
                'iso_code' => 'ISO 3166-2:KH'
            ),
            'Cameroon' => array(
                'alpha_2' => 'CM',
                'alpha_3' => 'CMR',
                'num_code' => '120',
                'calling_code' => '237',
                'iso_code' => 'ISO 3166-2:CM'
            ),
            'Canada' => array(
                'alpha_2' => 'CA',
                'alpha_3' => 'CAN',
                'num_code' => '124',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:CA'
            ),
            'Cape Verde' => array(
                'alpha_2' => 'CV',
                'alpha_3' => 'CPV',
                'num_code' => '132',
                'calling_code' => '238',
                'iso_code' => 'ISO 3166-2:CV'
            ),
            'Cayman Islands' => array(
                'alpha_2' => 'KY',
                'alpha_3' => 'CYM',
                'num_code' => '136',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:KY'
            ),
            'Central African Republic' => array(
                'alpha_2' => 'CF',
                'alpha_3' => 'CAF',
                'num_code' => '140',
                'calling_code' => '236',
                'iso_code' => 'ISO 3166-2:CF'
            ),
            'Chad' => array(
                'alpha_2' => 'TD',
                'alpha_3' => 'TCD',
                'num_code' => '148',
                'calling_code' => '235',
                'iso_code' => 'ISO 3166-2:TD'
            ),
            'Chile' => array(
                'alpha_2' => 'CL',
                'alpha_3' => 'CHL',
                'num_code' => '152',
                'calling_code' => '56',
                'iso_code' => 'ISO 3166-2:CL'
            ),
            'China' => array(
                'alpha_2' => 'CN',
                'alpha_3' => 'CHN',
                'num_code' => '156',
                'calling_code' => '86',
                'iso_code' => 'ISO 3166-2:CN'
            ),
            'Christmas Island' => array(
                'alpha_2' => 'CX',
                'alpha_3' => 'CXR',
                'num_code' => '162',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:CX'
            ),
            'Cocos (Keeling) Islands' => array(
                'alpha_2' => 'CC',
                'alpha_3' => 'CCK',
                'num_code' => '166',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:CC'
            ),
            'Colombia' => array(
                'alpha_2' => 'CO',
                'alpha_3' => 'COL',
                'num_code' => '170',
                'calling_code' => '57',
                'iso_code' => 'ISO 3166-2:CO'
            ),
            'Comoros' => array(
                'alpha_2' => 'KM',
                'alpha_3' => 'COM',
                'num_code' => '174',
                'calling_code' => '269',
                'iso_code' => 'ISO 3166-2:KM'
            ),
            'Congo' => array(
                'alpha_2' => 'CG',
                'alpha_3' => 'COG',
                'num_code' => '178',
                'calling_code' => '242',
                'iso_code' => 'ISO 3166-2:CG'
            ),
            'Congo, the Democratic Republic of the' => array(
                'alpha_2' => 'CD',
                'alpha_3' => 'COD',
                'num_code' => '180',
                'calling_code' => '243',
                'iso_code' => 'ISO 3166-2:CD'
            ),
            'Cook Islands' => array(
                'alpha_2' => 'CK',
                'alpha_3' => 'COK',
                'num_code' => '184',
                'calling_code' => '682',
                'iso_code' => 'ISO 3166-2:CK'
            ),
            'Costa Rica' => array(
                'alpha_2' => 'CR',
                'alpha_3' => 'CRI',
                'num_code' => '188',
                'calling_code' => '506',
                'iso_code' => 'ISO 3166-2:CR'
            ),
            'Cote d\'Ivoire' => array(
                'alpha_2' => 'CI',
                'alpha_3' => 'CIV',
                'num_code' => '384',
                'calling_code' => '225',
                'iso_code' => 'ISO 3166-2:CI'
            ),
            'Croatia' => array(
                'alpha_2' => 'HR',
                'alpha_3' => 'HRV',
                'num_code' => '191',
                'calling_code' => '385',
                'iso_code' => 'ISO 3166-2:HR'
            ),
            'Cuba' => array(
                'alpha_2' => 'CU',
                'alpha_3' => 'CUB',
                'num_code' => '192',
                'calling_code' => '53',
                'iso_code' => 'ISO 3166-2:CU'
            ),
            'Curacao' => array(
                'alpha_2' => 'CW',
                'alpha_3' => 'CUW',
                'num_code' => '531',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:CW'
            ),
            'Cyprus' => array(
                'alpha_2' => 'CY',
                'alpha_3' => 'CYP',
                'num_code' => '196',
                'calling_code' => '357',
                'iso_code' => 'ISO 3166-2:CY'
            ),
            'Czech Republic' => array(
                'alpha_2' => 'CZ',
                'alpha_3' => 'CZE',
                'num_code' => '203',
                'calling_code' => '420',
                'iso_code' => 'ISO 3166-2:CZ'
            ),
            'Denmark' => array(
                'alpha_2' => 'DK',
                'alpha_3' => 'DNK',
                'num_code' => '208',
                'calling_code' => '45',
                'iso_code' => 'ISO 3166-2:DK'
            ),
            'Djibouti' => array(
                'alpha_2' => 'DJ',
                'alpha_3' => 'DJI',
                'num_code' => '262',
                'calling_code' => '253',
                'iso_code' => 'ISO 3166-2:DJ'
            ),
            'Dominica' => array(
                'alpha_2' => 'DM',
                'alpha_3' => 'DMA',
                'num_code' => '212',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:DM'
            ),
            'Dominican Republic' => array(
                'alpha_2' => 'DO',
                'alpha_3' => 'DOM',
                'num_code' => '214',
                'calling_code' => '1809',
                'iso_code' => 'ISO 3166-2:DO'
            ),
            'Ecuador' => array(
                'alpha_2' => 'EC',
                'alpha_3' => 'ECU',
                'num_code' => '218',
                'calling_code' => '593',
                'iso_code' => 'ISO 3166-2:EC'
            ),
            'Egypt' => array(
                'alpha_2' => 'EG',
                'alpha_3' => 'EGY',
                'num_code' => '818',
                'calling_code' => '20',
                'iso_code' => 'ISO 3166-2:EG'
            ),
            'El Salvador' => array(
                'alpha_2' => 'SV',
                'alpha_3' => 'SLV',
                'num_code' => '222',
                'calling_code' => '503',
                'iso_code' => 'ISO 3166-2:SV'
            ),
            'Equatorial Guinea' => array(
                'alpha_2' => 'GQ',
                'alpha_3' => 'GNQ',
                'num_code' => '226',
                'calling_code' => '240',
                'iso_code' => 'ISO 3166-2:GQ'
            ),
            'Eritrea' => array(
                'alpha_2' => 'ER',
                'alpha_3' => 'ERI',
                'num_code' => '232',
                'calling_code' => '291',
                'iso_code' => 'ISO 3166-2:ER'
            ),
            'Estonia' => array(
                'alpha_2' => 'EE',
                'alpha_3' => 'EST',
                'num_code' => '233',
                'calling_code' => '372',
                'iso_code' => 'ISO 3166-2:EE'
            ),
            'Ethiopia' => array(
                'alpha_2' => 'ET',
                'alpha_3' => 'ETH',
                'num_code' => '231',
                'calling_code' => '251',
                'iso_code' => 'ISO 3166-2:ET'
            ),
            'Falkland Islands (Malvinas)' => array(
                'alpha_2' => 'FK',
                'alpha_3' => 'FLK',
                'num_code' => '238',
                'calling_code' => '500',
                'iso_code' => 'ISO 3166-2:FK'
            ),
            'Faroe Islands' => array(
                'alpha_2' => 'FO',
                'alpha_3' => 'FRO',
                'num_code' => '234',
                'calling_code' => '298',
                'iso_code' => 'ISO 3166-2:FO'
            ),
            'Fiji' => array(
                'alpha_2' => 'FJ',
                'alpha_3' => 'FJI',
                'num_code' => '242',
                'calling_code' => '679',
                'iso_code' => 'ISO 3166-2:FJ'
            ),
            'Finland' => array(
                'alpha_2' => 'FI',
                'alpha_3' => 'FIN',
                'num_code' => '246',
                'calling_code' => '358',
                'iso_code' => 'ISO 3166-2:FI'
            ),
            'France' => array(
                'alpha_2' => 'FR',
                'alpha_3' => 'FRA',
                'num_code' => '250',
                'calling_code' => '33',
                'iso_code' => 'ISO 3166-2:FR'
            ),
            'French Guiana' => array(
                'alpha_2' => 'GF',
                'alpha_3' => 'GUF',
                'num_code' => '254',
                'calling_code' => '594',
                'iso_code' => 'ISO 3166-2:GF'
            ),
            'French Polynesia' => array(
                'alpha_2' => 'PF',
                'alpha_3' => 'PYF',
                'num_code' => '258',
                'calling_code' => '689',
                'iso_code' => 'ISO 3166-2:PF'
            ),
            'French Southern Territories' => array(
                'alpha_2' => 'TF',
                'alpha_3' => 'ATF',
                'num_code' => '260',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:TF'
            ),
            'Gabon' => array(
                'alpha_2' => 'GA',
                'alpha_3' => 'GAB',
                'num_code' => '266',
                'calling_code' => '241',
                'iso_code' => 'ISO 3166-2:GA'
            ),
            'Gambia' => array(
                'alpha_2' => 'GM',
                'alpha_3' => 'GMB',
                'num_code' => '270',
                'calling_code' => '220',
                'iso_code' => 'ISO 3166-2:GM'
            ),
            'Georgia' => array(
                'alpha_2' => 'GE',
                'alpha_3' => 'GEO',
                'num_code' => '268',
                'calling_code' => '995',
                'iso_code' => 'ISO 3166-2:GE'
            ),
            'Germany' => array(
                'alpha_2' => 'DE',
                'alpha_3' => 'DEU',
                'num_code' => '276',
                'calling_code' => '49',
                'iso_code' => 'ISO 3166-2:DE'
            ),
            'Ghana' => array(
                'alpha_2' => 'GH',
                'alpha_3' => 'GHA',
                'num_code' => '288',
                'calling_code' => '49',
                'iso_code' => 'ISO 3166-2:GH'
            ),
            'Gibraltar' => array(
                'alpha_2' => 'GI',
                'alpha_3' => 'GIB',
                'num_code' => '292',
                'calling_code' => '350',
                'iso_code' => 'ISO 3166-2:GI'
            ),
            'Greece' => array(
                'alpha_2' => 'GR',
                'alpha_3' => 'GRC',
                'num_code' => '300',
                'calling_code' => '30',
                'iso_code' => 'ISO 3166-2:GR'
            ),
            'Greenland' => array(
                'alpha_2' => 'GL',
                'alpha_3' => 'GRL',
                'num_code' => '304',
                'calling_code' => '299',
                'iso_code' => 'ISO 3166-2:GL'
            ),
            'Grenada' => array(
                'alpha_2' => 'GD',
                'alpha_3' => 'GRD',
                'num_code' => '308',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:GD'
            ),
            'Guadeloupe' => array(
                'alpha_2' => 'GP',
                'alpha_3' => 'GLP',
                'num_code' => '312',
                'calling_code' => '590',
                'iso_code' => 'ISO 3166-2:GP'
            ),
            'Guam' => array(
                'alpha_2' => 'GU',
                'alpha_3' => 'GUM',
                'num_code' => '316',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:GU'
            ),
            'Guatemala' => array(
                'alpha_2' => 'GT',
                'alpha_3' => 'GTM',
                'num_code' => '320',
                'calling_code' => '502',
                'iso_code' => 'ISO 3166-2:GT'
            ),
            'Guernsey' => array(
                'alpha_2' => 'GG',
                'alpha_3' => 'GGY',
                'num_code' => '831',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:GG'
            ),
            'Guinea' => array(
                'alpha_2' => 'GN',
                'alpha_3' => 'GIN',
                'num_code' => '324',
                'calling_code' => '224',
                'iso_code' => 'ISO 3166-2:GN'
            ),
            'Guinea-Bissau' => array(
                'alpha_2' => 'GW',
                'alpha_3' => 'GNB',
                'num_code' => '624',
                'calling_code' => '245',
                'iso_code' => 'ISO 3166-2:GW'
            ),
            'Guyana' => array(
                'alpha_2' => 'GY',
                'alpha_3' => 'GUY',
                'num_code' => '328',
                'calling_code' => '592',
                'iso_code' => 'ISO 3166-2:GY'
            ),
            'Haiti' => array(
                'alpha_2' => 'HT',
                'alpha_3' => 'HTI',
                'num_code' => '332',
                'calling_code' => '509',
                'iso_code' => 'ISO 3166-2:HT'
            ),
            'Heard Island and McDonald Islands' => array(
                'alpha_2' => 'HM',
                'alpha_3' => 'HMD',
                'num_code' => '334',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:HM'
            ),
            'Holy See (Vatican City State)' => array(
                'alpha_2' => 'VA',
                'alpha_3' => 'VAT',
                'num_code' => '336',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:VA'
            ),
            'Honduras' => array(
                'alpha_2' => 'HN',
                'alpha_3' => 'HND',
                'num_code' => '340',
                'calling_code' => '504',
                'iso_code' => 'ISO 3166-2:HN'
            ),
            'Hong Kong' => array(
                'alpha_2' => 'HK',
                'alpha_3' => 'HKG',
                'num_code' => '344',
                'calling_code' => '852',
                'iso_code' => 'ISO 3166-2:HK'
            ),
            'Hungary' => array(
                'alpha_2' => 'HU',
                'alpha_3' => 'HUN',
                'num_code' => '348',
                'calling_code' => '36',
                'iso_code' => 'ISO 3166-2:HU'
            ),
            'Iceland' => array(
                'alpha_2' => 'IS',
                'alpha_3' => 'ISL',
                'num_code' => '352',
                'calling_code' => '354',
                'iso_code' => 'ISO 3166-2:IS'
            ),
            'India' => array(
                'alpha_2' => 'IN',
                'alpha_3' => 'IND',
                'num_code' => '356',
                'calling_code' => '91',
                'iso_code' => 'ISO 3166-2:IN'
            ),
            'Indonesia' => array(
                'alpha_2' => 'ID',
                'alpha_3' => 'IDN',
                'num_code' => '360',
                'calling_code' => '62',
                'iso_code' => 'ISO 3166-2:ID'
            ),
            'Iran, Islamic Republic of' => array(
                'alpha_2' => 'IR',
                'alpha_3' => 'IRN',
                'num_code' => '364',
                'calling_code' => '98',
                'iso_code' => 'ISO 3166-2:IR'
            ),
            'Iraq' => array(
                'alpha_2' => 'IQ',
                'alpha_3' => 'IRQ',
                'num_code' => '368',
                'calling_code' => '964',
                'iso_code' => 'ISO 3166-2:IQ'
            ),
            'Ireland' => array(
                'alpha_2' => 'IE',
                'alpha_3' => 'IRL',
                'num_code' => '372',
                'calling_code' => '353',
                'iso_code' => 'ISO 3166-2:IE'
            ),
            'Isle of Man' => array(
                'alpha_2' => 'IM',
                'alpha_3' => 'IMN',
                'num_code' => '833',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:IM'
            ),
            'Israel' => array(
                'alpha_2' => 'IL',
                'alpha_3' => 'ISR',
                'num_code' => '376',
                'calling_code' => '972',
                'iso_code' => 'ISO 3166-2:IL'
            ),
            'Italy' => array(
                'alpha_2' => 'IT',
                'alpha_3' => 'ITA',
                'num_code' => '380',
                'calling_code' => '39',
                'iso_code' => 'ISO 3166-2:IT'
            ),
            'Jamaica' => array(
                'alpha_2' => 'JM',
                'alpha_3' => 'JAM',
                'num_code' => '388',
                'calling_code' => '1876',
                'iso_code' => 'ISO 3166-2:JM'
            ),
            'Japan' => array(
                'alpha_2' => 'JP',
                'alpha_3' => 'JPN',
                'num_code' => '392',
                'calling_code' => '81',
                'iso_code' => 'ISO 3166-2:JP'
            ),
            'Jersey' => array(
                'alpha_2' => 'JE',
                'alpha_3' => 'JEY',
                'num_code' => '832',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:JE'
            ),
            'Jordan' => array(
                'alpha_2' => 'JO',
                'alpha_3' => 'JOR',
                'num_code' => '400',
                'calling_code' => '962',
                'iso_code' => 'ISO 3166-2:JO'
            ),
            'Kazakhstan' => array(
                'alpha_2' => 'KZ',
                'alpha_3' => 'KAZ',
                'num_code' => '398',
                'calling_code' => '7',
                'iso_code' => 'ISO 3166-2:KZ'
            ),
            'Kenya' => array(
                'alpha_2' => 'KE',
                'alpha_3' => 'KEN',
                'num_code' => '404',
                'calling_code' => '254',
                'iso_code' => 'ISO 3166-2:KE'
            ),
            'Kiribati' => array(
                'alpha_2' => 'KI',
                'alpha_3' => 'KIR',
                'num_code' => '296',
                'calling_code' => '686',
                'iso_code' => 'ISO 3166-2:KI'
            ),
            'Korea, Democratic People\'s Republic of' => array(
                // aka North Korea
                'alpha_2' => 'KP',
                'alpha_3' => 'PRK',
                'num_code' => '408',
                'calling_code' => '850',
                'iso_code' => 'ISO 3166-2:KP'
            ),
            'Korea, Republic of' => array(
                // aka South Korea
                'alpha_2' => 'KR',
                'alpha_3' => 'KOR',
                'num_code' => '410',
                'calling_code' => '82',
                'iso_code' => 'ISO 3166-2:KR'
            ),
            'Kuwait' => array(
                'alpha_2' => 'KW',
                'alpha_3' => 'KWT',
                'num_code' => '414',
                'calling_code' => '965',
                'iso_code' => 'ISO 3166-2:KW'
            ),
            'Kyrgyzstan' => array(
                'alpha_2' => 'KG',
                'alpha_3' => 'KGZ',
                'num_code' => '417',
                'calling_code' => '996',
                'iso_code' => 'ISO 3166-2:KG'
            ),
            'Lao People\'s Democratic Republic' => array(
                'alpha_2' => 'LA',
                'alpha_3' => 'LAO',
                'num_code' => '418',
                'calling_code' => '856',
                'iso_code' => 'ISO 3166-2:LA'
            ),
            'Latvia' => array(
                'alpha_2' => 'LV',
                'alpha_3' => 'LVA',
                'num_code' => '428',
                'calling_code' => '371',
                'iso_code' => 'ISO 3166-2:LV'
            ),
            'Lebanon' => array(
                'alpha_2' => 'LB',
                'alpha_3' => 'LBN',
                'num_code' => '422',
                'calling_code' => '961',
                'iso_code' => 'ISO 3166-2:LB'
            ),
            'Lesotho' => array(
                'alpha_2' => 'LS',
                'alpha_3' => 'LSO',
                'num_code' => '426',
                'calling_code' => '226',
                'iso_code' => 'ISO 3166-2:LS'
            ),
            'Liberia' => array(
                'alpha_2' => 'LR',
                'alpha_3' => 'LBR',
                'num_code' => '430',
                'calling_code' => '231',
                'iso_code' => 'ISO 3166-2:LR'
            ),
            'Libyan Arab Jamahiriya' => array(
                'alpha_2' => 'LY',
                'alpha_3' => 'LBY',
                'num_code' => '434',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:LY'
            ),
            'Liechtenstein' => array(
                'alpha_2' => 'LI',
                'alpha_3' => 'LIE',
                'num_code' => '438',
                'calling_code' => '423',
                'iso_code' => 'ISO 3166-2:LI'
            ),
            'Lithuania' => array(
                'alpha_2' => 'LT',
                'alpha_3' => 'LTU',
                'num_code' => '440',
                'calling_code' => '370',
                'iso_code' => 'ISO 3166-2:LT'
            ),
            'Luxembourg' => array(
                'alpha_2' => 'LU',
                'alpha_3' => 'LUX',
                'num_code' => '442',
                'calling_code' => '352',
                'iso_code' => 'ISO 3166-2:LU'
            ),
            'Macao' => array(
                'alpha_2' => 'MO',
                'alpha_3' => 'MAC',
                'num_code' => '446',
                'calling_code' => '853',
                'iso_code' => 'ISO 3166-2:MO'
            ),
            'Macedonia, the former Yugoslav Republic of' => array(
                'alpha_2' => 'MK',
                'alpha_3' => 'MKD',
                'num_code' => '807',
                'calling_code' => '389',
                'iso_code' => 'ISO 3166-2:MK'
            ),
            'Madagascar' => array(
                'alpha_2' => 'MG',
                'alpha_3' => 'MDG',
                'num_code' => '450',
                'calling_code' => '261',
                'iso_code' => 'ISO 3166-2:MG'
            ),
            'Malawi' => array(
                'alpha_2' => 'MW',
                'alpha_3' => 'MWI',
                'num_code' => '454',
                'calling_code' => '265',
                'iso_code' => 'ISO 3166-2:MW'
            ),
            'Malaysia' => array(
                'alpha_2' => 'MY',
                'alpha_3' => 'MYS',
                'num_code' => '458',
                'calling_code' => '60',
                'iso_code' => 'ISO 3166-2:MY'
            ),
            'Maldives' => array(
                'alpha_2' => 'MV',
                'alpha_3' => 'MDV',
                'num_code' => '462',
                'calling_code' => '960',
                'iso_code' => 'ISO 3166-2:MV'
            ),
            'Mali' => array(
                'alpha_2' => 'ML',
                'alpha_3' => 'MLI',
                'num_code' => '466',
                'calling_code' => '223',
                'iso_code' => 'ISO 3166-2:ML'
            ),
            'Malta' => array(
                'alpha_2' => 'MT',
                'alpha_3' => 'MLT',
                'num_code' => '470',
                'calling_code' => '356',
                'iso_code' => 'ISO 3166-2:MT'
            ),
            'Marshall Islands' => array(
                'alpha_2' => 'MH',
                'alpha_3' => 'MHL',
                'num_code' => '584',
                'calling_code' => '692',
                'iso_code' => 'ISO 3166-2:MH'
            ),
            'Martinique' => array(
                'alpha_2' => 'MQ',
                'alpha_3' => 'MTQ',
                'num_code' => '474',
                'calling_code' => '596',
                'iso_code' => 'ISO 3166-2:MQ'
            ),
            'Mauritania' => array(
                'alpha_2' => 'MR',
                'alpha_3' => 'MRT',
                'num_code' => '478',
                'calling_code' => '222',
                'iso_code' => 'ISO 3166-2:MR'
            ),
            'Mauritius' => array(
                'alpha_2' => 'MU',
                'alpha_3' => 'MUS',
                'num_code' => '480',
                'calling_code' => '230',
                'iso_code' => 'ISO 3166-2:MU'
            ),
            'Mayotte' => array(
                'alpha_2' => 'YT',
                'alpha_3' => 'MYT',
                'num_code' => '175',
                'calling_code' => '262',
                'iso_code' => 'ISO 3166-2:YT'
            ),
            'Mexico' => array(
                'alpha_2' => 'MX',
                'alpha_3' => 'MEX',
                'num_code' => '484',
                'calling_code' => '52',
                'iso_code' => 'ISO 3166-2:MX'
            ),
            'Micronesia, Federated States of' => array(
                'alpha_2' => 'FM',
                'alpha_3' => 'FSM',
                'num_code' => '583',
                'calling_code' => '961',
                'iso_code' => 'ISO 3166-2:FM'
            ),
            'Moldova, Republic of' => array(
                'alpha_2' => 'MD',
                'alpha_3' => 'MDA',
                'num_code' => '498',
                'calling_code' => '373',
                'iso_code' => 'ISO 3166-2:MD'
            ),
            'Monaco' => array(
                'alpha_2' => 'MC',
                'alpha_3' => 'MCO',
                'num_code' => '492',
                'calling_code' => '377',
                'iso_code' => 'ISO 3166-2:MC'
            ),
            'Mongolia' => array(
                'alpha_2' => 'MN',
                'alpha_3' => 'MNG',
                'num_code' => '496',
                'calling_code' => '976',
                'iso_code' => 'ISO 3166-2:MN'
            ),
            'Montenegro' => array(
                'alpha_2' => 'ME',
                'alpha_3' => 'MNE',
                'num_code' => '499',
                'calling_code' => '382',
                'iso_code' => 'ISO 3166-2:ME'
            ),
            'Montserrat' => array(
                'alpha_2' => 'MS',
                'alpha_3' => 'MSR',
                'num_code' => '500',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:MS'
            ),
            'Morocco' => array(
                'alpha_2' => 'MA',
                'alpha_3' => 'MAR',
                'num_code' => '504',
                'calling_code' => '212',
                'iso_code' => 'ISO 3166-2:MA'
            ),
            'Mozambique' => array(
                'alpha_2' => 'MZ',
                'alpha_3' => 'MOZ',
                'num_code' => '508',
                'calling_code' => '258',
                'iso_code' => 'ISO 3166-2:MZ'
            ),
            'Myanmar' => array(
                'alpha_2' => 'MM',
                'alpha_3' => 'MMR',
                'num_code' => '104',
                'calling_code' => '95',
                'iso_code' => 'ISO 3166-2:MM'
            ),
            'Namibia' => array(
                'alpha_2' => 'NA',
                'alpha_3' => 'NAM',
                'num_code' => '516',
                'calling_code' => '264',
                'iso_code' => 'ISO 3166-2:NA'
            ),
            'Nauru' => array(
                'alpha_2' => 'NR',
                'alpha_3' => 'NRU',
                'num_code' => '520',
                'calling_code' => '674',
                'iso_code' => 'ISO 3166-2:NR'
            ),
            'Nepal' => array(
                'alpha_2' => 'NP',
                'alpha_3' => 'NPL',
                'num_code' => '524',
                'calling_code' => '977',
                'iso_code' => 'ISO 3166-2:NP'
            ),
            'Netherlands' => array(
                'alpha_2' => 'NL',
                'alpha_3' => 'NLD',
                'num_code' => '528',
                'calling_code' => '599',
                'iso_code' => 'ISO 3166-2:NL'
            ),
            'New Caledonia' => array(
                'alpha_2' => 'NC',
                'alpha_3' => 'NCL',
                'num_code' => '540',
                'calling_code' => '687',
                'iso_code' => 'ISO 3166-2:NC'
            ),
            'New Zealand' => array(
                'alpha_2' => 'NZ',
                'alpha_3' => 'NZL',
                'num_code' => '554',
                'calling_code' => '64',
                'iso_code' => 'ISO 3166-2:NZ'
            ),
            'Nicaragua' => array(
                'alpha_2' => 'NI',
                'alpha_3' => 'NIC',
                'num_code' => '558',
                'calling_code' => '505',
                'iso_code' => 'ISO 3166-2:NI'
            ),
            'Niger' => array(
                'alpha_2' => 'NE',
                'alpha_3' => 'NER',
                'num_code' => '562',
                'calling_code' => '227',
                'iso_code' => 'ISO 3166-2:NE'
            ),
            'Nigeria' => array(
                'alpha_2' => 'NG',
                'alpha_3' => 'NGA',
                'num_code' => '566',
                'calling_code' => '234',
                'iso_code' => 'ISO 3166-2:NG'
            ),
            'Niue' => array(
                'alpha_2' => 'NU',
                'alpha_3' => 'NIU',
                'num_code' => '570',
                'calling_code' => '683',
                'iso_code' => 'ISO 3166-2:NU'
            ),
            'Norfolk Island' => array(
                'alpha_2' => 'NF',
                'alpha_3' => 'NFK',
                'num_code' => '574',
                'calling_code' => '6723',
                'iso_code' => 'ISO 3166-2:NF'
            ),
            'Northern Mariana Islands' => array(
                'alpha_2' => 'MP',
                'alpha_3' => 'MNP',
                'num_code' => '580',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:MP'
            ),
            'Norway' => array(
                'alpha_2' => 'NO',
                'alpha_3' => 'NOR',
                'num_code' => '578',
                'calling_code' => '47',
                'iso_code' => 'ISO 3166-2:NO'
            ),
            'Oman' => array(
                'alpha_2' => 'OM',
                'alpha_3' => 'OMN',
                'num_code' => '512',
                'calling_code' => '968',
                'iso_code' => 'ISO 3166-2:OM'
            ),
            'Pakistan' => array(
                'alpha_2' => 'PK',
                'alpha_3' => 'PAK',
                'num_code' => '586',
                'calling_code' => '92',
                'iso_code' => 'ISO 3166-2:PK'
            ),
            'Palau' => array(
                'alpha_2' => 'PW',
                'alpha_3' => 'PLW',
                'num_code' => '585',
                'calling_code' => '680',
                'iso_code' => 'ISO 3166-2:PW'
            ),
            'Palestinian Territory, Occupied' => array(
                'alpha_2' => 'PS',
                'alpha_3' => 'PSE',
                'num_code' => '275',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:PS'
            ),
            'Panama' => array(
                'alpha_2' => 'PA',
                'alpha_3' => 'PAN',
                'num_code' => '591',
                'calling_code' => '507',
                'iso_code' => 'ISO 3166-2:PA'
            ),
            'Papua New Guinea' => array(
                'alpha_2' => 'PG',
                'alpha_3' => 'PNG',
                'num_code' => '598',
                'calling_code' => '675',
                'iso_code' => 'ISO 3166-2:PG'
            ),
            'Paraguay' => array(
                'alpha_2' => 'PY',
                'alpha_3' => 'PRY',
                'num_code' => '600',
                'calling_code' => '595',
                'iso_code' => 'ISO 3166-2:PY'
            ),
            'Peru' => array(
                'alpha_2' => 'PE',
                'alpha_3' => 'PER',
                'num_code' => '604',
                'calling_code' => '51',
                'iso_code' => 'ISO 3166-2:PE'
            ),
            'Philippines' => array(
                'alpha_2' => 'PH',
                'alpha_3' => 'PHL',
                'num_code' => '608',
                'calling_code' => '63',
                'iso_code' => 'ISO 3166-2:PH'
            ),
            'Pitcairn' => array(
                'alpha_2' => 'PN',
                'alpha_3' => 'PCN',
                'num_code' => '612',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:PN'
            ),
            'Poland' => array(
                'alpha_2' => 'PL',
                'alpha_3' => 'POL',
                'num_code' => '616',
                'calling_code' => '48',
                'iso_code' => 'ISO 3166-2:PL'
            ),
            'Portugal' => array(
                'alpha_2' => 'PT',
                'alpha_3' => 'PRT',
                'num_code' => '620',
                'calling_code' => '351',
                'iso_code' => 'ISO 3166-2:PT'
            ),
            'Puerto Rico' => array(
                'alpha_2' => 'PR',
                'alpha_3' => 'PRI',
                'num_code' => '630',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:PR'
            ),
            'Qatar' => array(
                'alpha_2' => 'QA',
                'alpha_3' => 'QAT',
                'num_code' => '634',
                'calling_code' => '974',
                'iso_code' => 'ISO 3166-2:QA'
            ),
            'Reunion' => array(
                'alpha_2' => 'RE',
                'alpha_3' => 'REU',
                'num_code' => '638',
                'calling_code' => '262',
                'iso_code' => 'ISO 3166-2:RE'
            ),
            'Romania' => array(
                'alpha_2' => 'RO',
                'alpha_3' => 'ROU',
                'num_code' => '642',
                'calling_code' => '40',
                'iso_code' => 'ISO 3166-2:RO'
            ),
            'Russian Federation' => array(
                'alpha_2' => 'RU',
                'alpha_3' => 'RUS',
                'num_code' => '643',
                'calling_code' => '7',
                'iso_code' => 'ISO 3166-2:RU'
            ),
            'Rwanda' => array(
                'alpha_2' => 'RW',
                'alpha_3' => 'RWA',
                'num_code' => '646',
                'calling_code' => '250',
                'iso_code' => 'ISO 3166-2:RW'
            ),
            'Saint Barthelemy' => array(
                'alpha_2' => 'BL',
                'alpha_3' => 'BLM',
                'num_code' => '652',
                'calling_code' => '590',
                'iso_code' => 'ISO 3166-2:BL'
            ),
            'Saint Helena, Ascension and Tristan da Cunha' => array(
                'alpha_2' => 'SH',
                'alpha_3' => 'SHN',
                'num_code' => '654',
                'calling_code' => '290',
                'iso_code' => 'ISO 3166-2:SH'
            ),
            'Saint Kitts and Nevis' => array(
                'alpha_2' => 'KN',
                'alpha_3' => 'KNA',
                'num_code' => '659',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:KN'
            ),
            'Saint Lucia' => array(
                'alpha_2' => 'LC',
                'alpha_3' => 'LCA',
                'num_code' => '662',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:LC'
            ),
            'Saint Martin (French part)' => array(
                'alpha_2' => 'MF',
                'alpha_3' => 'MAF',
                'num_code' => '663',
                'calling_code' => '590',
                'iso_code' => 'ISO 3166-2:MF'
            ),
            'Saint Pierre and Miquelon' => array(
                'alpha_2' => 'PM',
                'alpha_3' => 'SPM',
                'num_code' => '666',
                'calling_code' => '508',
                'iso_code' => 'ISO 3166-2:PM'
            ),
            'Saint Vincent and the Grenadines' => array(
                'alpha_2' => 'VC',
                'alpha_3' => 'VCT',
                'num_code' => '670',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:VC'
            ),
            'Samoa' => array(
                'alpha_2' => 'WS',
                'alpha_3' => 'WSM',
                'num_code' => '882',
                'calling_code' => '685',
                'iso_code' => 'ISO 3166-2:WS'
            ),
            'San Marino' => array(
                'alpha_2' => 'SM',
                'alpha_3' => 'SMR',
                'num_code' => '674',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:SM'
            ),
            'Sao Tome and Principe' => array(
                'alpha_2' => 'ST',
                'alpha_3' => 'STP',
                'num_code' => '678',
                'calling_code' => '239',
                'iso_code' => 'ISO 3166-2:ST'
            ),
            'Saudi Arabia' => array(
                'alpha_2' => 'SA',
                'alpha_3' => 'SAU',
                'num_code' => '682',
                'calling_code' => '966',
                'iso_code' => 'ISO 3166-2:SA'
            ),
            'Senegal' => array(
                'alpha_2' => 'SN',
                'alpha_3' => 'SEN',
                'num_code' => '686',
                'calling_code' => '221',
                'iso_code' => 'ISO 3166-2:SN'
            ),
            'Serbia' => array(
                'alpha_2' => 'RS',
                'alpha_3' => 'SRB',
                'num_code' => '381',
                'calling_code' => '381',
                'iso_code' => 'ISO 3166-2:RS'
            ),
            'Seychelles' => array(
                'alpha_2' => 'SC',
                'alpha_3' => 'SYC',
                'num_code' => '690',
                'calling_code' => '248',
                'iso_code' => 'ISO 3166-2:SC'
            ),
            'Sierra Leone' => array(
                'alpha_2' => 'SL',
                'alpha_3' => 'SLE',
                'num_code' => '694',
                'calling_code' => '232',
                'iso_code' => 'ISO 3166-2:SL'
            ),
            'Singapore' => array(
                'alpha_2' => 'SG',
                'alpha_3' => 'SGP',
                'num_code' => '702',
                'calling_code' => '65',
                'iso_code' => 'ISO 3166-2:SG'
            ),
            'Sint Maarten (Dutch part)' => array(
                'alpha_2' => 'SX',
                'alpha_3' => 'SXM',
                'num_code' => '534',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:SX'
            ),
            'Slovakia' => array(
                'alpha_2' => 'SK',
                'alpha_3' => 'SVK',
                'num_code' => '703',
                'calling_code' => '421',
                'iso_code' => 'ISO 3166-2:SK'
            ),
            'Slovenia' => array(
                'alpha_2' => 'SI',
                'alpha_3' => 'SVN',
                'num_code' => '705',
                'calling_code' => '386',
                'iso_code' => 'ISO 3166-2:SI'
            ),
            'Solomon Islands' => array(
                'alpha_2' => 'SB',
                'alpha_3' => 'SLB',
                'num_code' => '090',
                'calling_code' => '677',
                'iso_code' => 'ISO 3166-2:SB'
            ),
            'Somalia' => array(
                'alpha_2' => 'SO',
                'alpha_3' => 'SOM',
                'num_code' => '706',
                'calling_code' => '252',
                'iso_code' => 'ISO 3166-2:SO'
            ),
            'South Africa' => array(
                'alpha_2' => 'ZA',
                'alpha_3' => 'ZAF',
                'num_code' => '710',
                'calling_code' => '27',
                'iso_code' => 'ISO 3166-2:ZA'
            ),
            'South Georgia and the South Sandwich Islands' => array(
                'alpha_2' => 'GS',
                'alpha_3' => 'SGS',
                'num_code' => '239',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:GS'
            ),
            'Spain' => array(
                'alpha_2' => 'ES',
                'alpha_3' => 'ESP',
                'num_code' => '724',
                'calling_code' => '34',
                'iso_code' => 'ISO 3166-2:ES'
            ),
            'Sri Lanka' => array(
                'alpha_2' => 'LK',
                'alpha_3' => 'LKA',
                'num_code' => '144',
                'calling_code' => '94',
                'iso_code' => 'ISO 3166-2:LK'
            ),
            'Sudan' => array(
                'alpha_2' => 'SD',
                'alpha_3' => 'SDN',
                'num_code' => '736',
                'calling_code' => '249',
                'iso_code' => 'ISO 3166-2:SD'
            ),
            'Suriname' => array(
                'alpha_2' => 'SR',
                'alpha_3' => 'SUR',
                'num_code' => '740',
                'calling_code' => '597',
                'iso_code' => 'ISO 3166-2:SR'
            ),
            'Svalbard and Jan Mayen' => array(
                'alpha_2' => 'SJ',
                'alpha_3' => 'SJM',
                'num_code' => '744',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:SJ'
            ),
            'Swaziland' => array(
                'alpha_2' => 'SZ',
                'alpha_3' => 'SWZ',
                'num_code' => '748',
                'calling_code' => '268',
                'iso_code' => 'ISO 3166-2:SZ'
            ),
            'Sweden' => array(
                'alpha_2' => 'SE',
                'alpha_3' => 'SWE',
                'num_code' => '752',
                'calling_code' => '46',
                'iso_code' => 'ISO 3166-2:SE'
            ),
            'Switzerland' => array(
                'alpha_2' => 'CH',
                'alpha_3' => 'CHE',
                'num_code' => '756',
                'calling_code' => '41',
                'iso_code' => 'ISO 3166-2:CH'
            ),
            'Syrian Arab Republic' => array(
                'alpha_2' => 'SY',
                'alpha_3' => 'SYR',
                'num_code' => '760',
                'calling_code' => '249',
                'iso_code' => 'ISO 3166-2:SY'
            ),
            'Taiwan, Province of China' => array(
                'alpha_2' => 'TW',
                'alpha_3' => 'TWN',
                'num_code' => '158',
                'calling_code' => '886',
                'iso_code' => 'ISO 3166-2:TW'
            ),
            'Tajikistan' => array(
                'alpha_2' => 'TJ',
                'alpha_3' => 'TJK',
                'num_code' => '762',
                'calling_code' => '992',
                'iso_code' => 'ISO 3166-2:TJ'
            ),
            'Tanzania, United Republic of' => array(
                'alpha_2' => 'TZ',
                'alpha_3' => 'TZA',
                'num_code' => '834',
                'calling_code' => '255',
                'iso_code' => 'ISO 3166-2:TZ'
            ),
            'Thailand' => array(
                'alpha_2' => 'TH',
                'alpha_3' => 'THA',
                'num_code' => '764',
                'calling_code' => '66',
                'iso_code' => 'ISO 3166-2:TH'
            ),
            'Timor-Leste' => array(
                'alpha_2' => 'TL',
                'alpha_3' => 'TLS',
                'num_code' => '626',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:TL'
            ),
            'Togo' => array(
                'alpha_2' => 'TG',
                'alpha_3' => 'TGO',
                'num_code' => '768',
                'calling_code' => '228',
                'iso_code' => 'ISO 3166-2:TG'
            ),
            'Tokelau' => array(
                'alpha_2' => 'TK',
                'alpha_3' => 'TKL',
                'num_code' => '772',
                'calling_code' => '690',
                'iso_code' => 'ISO 3166-2:TK'
            ),
            'Tonga' => array(
                'alpha_2' => 'TO',
                'alpha_3' => 'TON',
                'num_code' => '776',
                'calling_code' => '676',
                'iso_code' => 'ISO 3166-2:TO'
            ),
            'Trinidad and Tobago' => array(
                'alpha_2' => 'TT',
                'alpha_3' => 'TTO',
                'num_code' => '780',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:TT'
            ),
            'Tunisia' => array(
                'alpha_2' => 'TN',
                'alpha_3' => 'TUN',
                'num_code' => '788',
                'calling_code' => '216',
                'iso_code' => 'ISO 3166-2:TN'
            ),
            'Turkey' => array(
                'alpha_2' => 'TR',
                'alpha_3' => 'TUR',
                'num_code' => '792',
                'calling_code' => '90',
                'iso_code' => 'ISO 3166-2:TR'
            ),
            'Turkmenistan' => array(
                'alpha_2' => 'TM',
                'alpha_3' => 'TKM',
                'num_code' => '795',
                'calling_code' => '993',
                'iso_code' => 'ISO 3166-2:TM'
            ),
            'Turks and Caicos Islands' => array(
                'alpha_2' => 'TC',
                'alpha_3' => 'TCA',
                'num_code' => '796',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:TC'
            ),
            'Tuvalu' => array(
                'alpha_2' => 'TV',
                'alpha_3' => 'TUV',
                'num_code' => '798',
                'calling_code' => '688',
                'iso_code' => 'ISO 3166-2:TV'
            ),
            'Uganda' => array(
                'alpha_2' => 'UG',
                'alpha_3' => 'UGA',
                'num_code' => '800',
                'calling_code' => '256',
                'iso_code' => 'ISO 3166-2:UG'
            ),
            'Ukraine' => array(
                'alpha_2' => 'UA',
                'alpha_3' => 'UKR',
                'num_code' => '804',
                'calling_code' => '380',
                'iso_code' => 'ISO 3166-2:UA'
            ),
            'United Arab Emirates' => array(
                'alpha_2' => 'AE',
                'alpha_3' => 'ARE',
                'num_code' => '784',
                'calling_code' => '971',
                'iso_code' => 'ISO 3166-2:AE'
            ),
            'United Kingdom' => array(
                'alpha_2' => 'GB',
                'alpha_3' => 'GBR',
                'num_code' => '826',
                'calling_code' => '44',
                'iso_code' => 'ISO 3166-2:GB'
            ),
            'United States' => array(
                'alpha_2' => 'US',
                'alpha_3' => 'USA',
                'num_code' => '840',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:US'
            ),
            'United States Minor Outlying Islands' => array(
                'alpha_2' => 'UM',
                'alpha_3' => 'UMI',
                'num_code' => '581',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:UM'
            ),
            'Uruguay' => array(
                'alpha_2' => 'UY',
                'alpha_3' => 'URY',
                'num_code' => '858',
                'calling_code' => '598',
                'iso_code' => 'ISO 3166-2:UY'
            ),
            'Uzbekistan' => array(
                'alpha_2' => 'UZ',
                'alpha_3' => 'UZB',
                'num_code' => '860',
                'calling_code' => '998',
                'iso_code' => 'ISO 3166-2:UZ'
            ),
            'Vanuatu' => array(
                'alpha_2' => 'VU',
                'alpha_3' => 'VUT',
                'num_code' => '548',
                'calling_code' => '678',
                'iso_code' => 'ISO 3166-2:VU'
            ),
            'Venezuela, Bolivarian Republic of' => array(
                'alpha_2' => 'VE',
                'alpha_3' => 'VEN',
                'num_code' => '862',
                'calling_code' => '58',
                'iso_code' => 'ISO 3166-2:VE'
            ),
            'Viet Nam' => array(
                'alpha_2' => 'VN',
                'alpha_3' => 'VNM',
                'num_code' => '704',
                'calling_code' => '84',
                'iso_code' => 'ISO 3166-2:VN'
            ),
            'Virgin Islands, British' => array(
                'alpha_2' => 'VG',
                'alpha_3' => 'VGB',
                'num_code' => '092',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:VG'
            ),
            'Virgin Islands, U.S.' => array(
                'alpha_2' => 'VI',
                'alpha_3' => 'VIR',
                'num_code' => '850',
                'calling_code' => '1',
                'iso_code' => 'ISO 3166-2:VI'
            ),
            'Wallis and Futuna' => array(
                'alpha_2' => 'WF',
                'alpha_3' => 'WLF',
                'num_code' => '876',
                'calling_code' => '681',
                'iso_code' => 'ISO 3166-2:WF'
            ),
            'Western Sahara' => array(
                'alpha_2' => 'EH',
                'alpha_3' => 'ESH',
                'num_code' => '732',
                'calling_code' => '',
                'iso_code' => 'ISO 3166-2:EH'
            ),
            'Yemen' => array(
                'alpha_2' => 'YE',
                'alpha_3' => 'YEM',
                'num_code' => '887',
                'calling_code' => '967',
                'iso_code' => 'ISO 3166-2:YE'
            ),
            'Zambia' => array(
                'alpha_2' => 'ZM',
                'alpha_3' => 'ZMB',
                'num_code' => '894',
                'calling_code' => '260',
                'iso_code' => 'ISO 3166-2:ZM'
            ),
            'Zimbabwe' => array(
                'alpha_2' => 'ZW',
                'alpha_3' => 'ZWE',
                'num_code' => '716',
                'calling_code' => '263',
                'iso_code' => 'ISO 3166-2:ZW'
            )
        );
    }
    /*
    'afghanistan' => '93', 'albania' => '355', 'algeria' => '213', 'andorra' => '376', 'angola' => '244',
    'anguilla' => '1', 'antarctica' => '6721', 'antigua and barbuda' => '1', 'argentina' => '54', 'armenia' => '374',
    'aruba' => '297', 'ascension' => '247', 'australia' => '61', 'austria' => '43', 'azerbaijan' => '994',
    'bahamas' => '1', 'bahrain' => '973', 'bangladesh' => '880', 'barbados' => '1', 'belarus' => '375',
    'belgium' => '32', 'belize' => '501', 'benin' => '229', 'bermuda' => '1', 'bhutan' => '975',
    'bolivia' => '591', 'bosnia and herzegovina' => '387', 'botswana' => '267', 'brazil' => '55', 'british indian ocean territory' => '246',
    'british virgin islands' => '1', 'brunei' => '673', 'bulgaria' => '359', 'burkina faso' => '226', 'burundi' => '257',
    'cambodia' => '855', 'cameroon' => '237', 'canada' => '1', 'cape verde' => '238', 'cayman islands' => '1',
    'central african republic' => '236', 'chad' => '235', 'chile' => '56', 'china' => '86', 'colombia' => '57',
    'comoros' => '269', 'congo, democratic republic of the' => '243', 'congo, republic of the' => '242', 'cook islands' => '682', 'costa rica' => '506',
    'cote d\'ivoire' => '225', 'croatia' => '385', 'cuba' => '53', 'cyprus' => '357', 'czech republic' => '420',
    'denmark' => '45', 'djibouti' => '253', 'dominica' => '1', 'dominican republic' => '1809', 'east timor' => '670',
    'ecuador' => '593', 'egypt' => '20', 'el salvador' => '503', 'equatorial guinea' => '240', 'eritrea' => '291',
    'estonia' => '372', 'ethiopia' => '251', 'falkland islands' => '500', 'faroe islands' => '298', 'fiji' => '679',
    'finland' => '358', 'france' => '33', 'french guiana' => '594', 'french polynesia' => '689', 'gabon' => '241',
    'gambia' => '220', 'gaza strip' => '970', 'georgia' => '995', 'germany' => '49', 'ghana' => '233',
    'gibraltar' => '350', 'greece' => '30', 'greenland' => '299', 'grenada' => '1', 'guadeloupe' => '590',
    'guatemala' => '502', 'guinea' => '224', 'guinea-bissau' => '245', 'guyana' => '592', 'haiti' => '509',
    'honduras' => '504', 'hong kong' => '852', 'hungary' => '36', 'iceland' => '354', 'india' => '91',
    'indonesia' => '62', 'iraq' => '964', 'iran' => '98', 'ireland (eire)' => '353', 'israel' => '972',
    'italy' => '39', 'jamaica' => '1876', 'japan' => '81', 'jordan' => '962', 'kazakhstan' => '7',
    'kenya' => '254', 'kiribati' => '686', 'kuwait' => '965', 'kyrgyzstan' => '996', 'laos' => '856',
    'latvia' => '371', 'lebanon' => '961', 'lesotho' => '226', 'liberia' => '231', 'liechtenstein' => '423',
    'lithuania' => '370', 'luxembourg' => '352', 'libya' => '218', 'macau' => '853', 'macedonia, republic of' => '389',
    'madagascar' => '261', 'malawi' => '265', 'malaysia' => '60', 'maldives' => '960', 'mali' => '223',
    'malta' => '356', 'marshall islands' => '692', 'martinique' => '596', 'mauritania' => '222', 'mauritius' => '230',
    'mayotte' => '262', 'mexico' => '52', 'micronesia, federated states of' => '691', 'moldova' => '373', 'monaco' => '377',
    'mongolia' => '976', 'montenegro' => '382', 'montserrat' => '1', 'morocco' => '212', 'mozambique' => '258',
    'myanmar' => '95', 'namibia' => '264', 'nauru' => '674', 'nl' => '31', 'netherlands' => '31', 'netherlands antilles' => '599',
    'nepal' => '977', 'new caledonia' => '687', 'new zealand' => '64', 'nicaragua' => '505', 'niger' => '227',
    'nigeria' => '234', 'niue' => '683', 'norfolk island' => '6723', 'north korea' => '850', 'northern ireland' => '44',
    'northern mariana islands' => '1', 'norway' => '47', 'oman' => '968', 'pakistan' => '92', 'palau' => '680',
    'panama' => '507', 'papua new guinea' => '675', 'paraguay' => '595', 'peru' => '51', 'philippines' => '63',
    'poland' => '48', 'portugal' => '351', 'qatar' => '974', 'reunion' => '262', 'romania' => '40',
    'russia' => '7', 'rwanda' => '250', 'saint barthelemy' => '590', 'saint helena' => '290', 'saint kitts and nevis' => '1',
    'saint lucia' => '1', 'saint martin (french side)' => '590', 'saint pierre and miquelon' => '508', 'saint vincent and the grenadines' => '1', 'samoa' => '685',
    'sao tome and principe' => '239', 'saudi arabia' => '966', 'senegal' => '221', 'serbia' => '381', 'seychelles' => '248',
    'sierra leone' => '232', 'singapore' => '65', 'slovakia' => '421', 'slovenia' => '386', 'solomon islands' => '677',
    'somalia' => '252', 'south africa' => '27', 'south korea' => '82', 'spain' => '34', 'sri lanka' => '94',
    'sudan' => '249', 'suriname' => '597', 'swaziland' => '268', 'sweden' => '46', 'switzerland' => '41',
    'syria' => '963', 'taiwan' => '886', 'tajikistan' => '992', 'tanzania' => '255', 'thailand' => '66',
    'togo' => '228', 'tokelau' => '690', 'tonga' => '676', 'trinidad and tobago' => '1', 'tunisia' => '216',
    'turkey' => '90', 'turkmenistan' => '993', 'turks and caicos islands' => '1', 'tuvalu' => '688', 'uganda' => '256',
    'ukraine' => '380', 'united arab emirates' => '971', 'gb' => '44', 'united kingdom' => '44',
    'united states of america' => '1', 'uruguay' => '598', 'uzbekistan' => '998', 'vanuatu' => '678', 'venezuela' => '58',
    'vietnam' => '84', 'virgin islands' => '1', 'wallis and futuna' => '681', 'west bank' => '970', 'yemen' => '967', 'zambia' => '260',
    'zimbabwe' => '263'
*/
}

?>
