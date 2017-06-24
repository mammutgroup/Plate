<?php
namespace Plate;

use Plate\Exception\CharIsNotValid;
use Plate\Exception\StateIsNotValid;
use Plate\Exception\CityNotFound;
use Plate\Exception\PlateIsNotValid;
use Plate\Exception\DateIsNotValid;

class Plate
{
    private $_plate = null;
    private $_resourcePath = null;
    private $_parsed = null;
    private $_data = null;
    private $_suportedChars = null;
    private $_engChars = null;
    private $_image = null;
    private $_date = null;

    public function __construct()
    {
        $config = config('plate');
        $this->_data = $config['state_data'];
        $this->_suportedChars = $config['supported_chars'];
        $this->_engChars = $config['eng_chars'];
        $this->_resourcePath = __DIR__ . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR;
    }

    private function parse()
    {
        preg_match($this->getRegEx(), $this->_plate, $matchs);

        $stateNumber = $matchs[4];
        $stateName = $this->_getStateNameByNumber($stateNumber);
        $character = $matchs[2];

        $this->_parsed = [
            'cityName' => $this->_getCityNameByCharAndNumber($stateName, $character, $stateNumber),
            'type' => $this->_getTypeByChar($character),
            'char' => $character,
            'charCode' => config('plate.char_codes.' . $character),
            '2DigitNumber' => $matchs[1],
            '3DigitNumber' => $matchs[3],
            'countryName' => $matchs[5],
            'stateNumber' => $stateNumber,
            'stateName' => $stateName,
        ];
    }

    private function _getTypeByChar($char)
    {
        if (!array_key_exists($char, $this->_suportedChars)) {
            throw new CharIsNotValid("This Char Is Not Valid");
        }

        return $this->_suportedChars[$char];
    }

    private function _getStateNameByNumber($number)
    {
        foreach ($this->_data as $stateName => $numbers) {
            if (array_key_exists($number, $numbers)) {
                return $stateName;
            }
        }

        throw new StateIsNotValid("There Is Not Any State With This Number");
    }

    private function _getCityNameByCharAndNumber($state, $char, $number)
    {
        if (empty($this->_data[$state][$number][$char][0])) {
            return '';
            throw new CityNotFound("There Is Not Any City With This Information");
        }

        return implode(', ', $this->_data[$state][$number][$char]);
    }

    public function setPlate($plate)
    {
        if (is_numeric($plate)) {
            $plate = $this->_plateFromCode($plate);
        }

        $this->_plate = $plate;
        $this->validate();
        $this->parse();
        return $this;
    }

    public function getRegEx()
    {
        $farsiChars = implode('|', array_keys($this->_suportedChars));
        return "/([1-9]\d{1})\s+({$farsiChars})\s+([1-9]\d{2})\s+\-\s+([1-9]\d{1}|10)\s+(ایران)/";
    }

    public function validate($plate = null, $softCheck = false)
    {
        if (empty($plate)) {
            $plate = $this->_plate;
        }

        preg_match($this->getRegEx(), $plate, $matchs);

        if (count($matchs) !== 6) {
            if ($softCheck) {
                return false;
            } else {
                throw new PlateIsNotValid("Plate Number Is Not Valid");
            }
        }
    }

    public function getparsedData()
    {
        return $this->_parsed;
    }

    public function getCountry()
    {
        return $this->_parsed['countryName'];
    }

    public function getCity()
    {
        return $this->_parsed['cityName'];
    }

    public function getState()
    {
        return $this->_parsed['stateName'];
    }

    public function getType()
    {
        return $this->_parsed['type'];
    }

    public function getStateNumber()
    {
        return $this->_parsed['stateNumber'];
    }

    public function get2DigitNumber()
    {
        return $this->_parsed['2DigitNumber'];
    }

    public function get3DigitNumber()
    {
        return $this->_parsed['3DigitNumber'];
    }

    public function isCab()
    {
        return $this->_parsed['type'] === 'تاکسی' ? true : false;
    }

    public function getImage()
    {
        $imageName = $this->_getImageNameBasedOnChar();
        $color = $this->_getColorNameBasedOnChar();
        $this->_image = \imagecreatefrompng($this->_resourcePath . $imageName);
        $width = \imagesx($this->_image);
        $height = \imagesy($this->_image);

        $this->_drawAllChars($color); // draw text
        $this->_drawEnglishPlate(); // draw eng plate

        //save image
        ob_start();
        \imagepng($this->_image, null);
        $content = ob_get_clean();
        \imagedestroy($this->_image);
        return $content;
    }

    public function withDate($date)
    {
        if (!preg_match('~^[0-9]{2}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$~', $date)) {
            throw new DateIsNotValid('Date format must be yy-mm-dd');
        }
        $this->_date = str_replace('-', '/', $date);
        return $this;
    }

    public function toNumber()
    {
        return $this->_parsed['stateNumber'] . $this->_parsed['charCode'] .$this->_parsed['2DigitNumber'] . $this->_parsed['3DigitNumber'];
    }

    public function toString()
    {
        return $this->_plate;
    }

    public function toArray()
    {
        $p = explode(' ', $this->_plate);
        $plateArray = [
            $p[5], // country
            $p[4], // ciity code
            $p[1], // char
            $p[2], // 3 digits
            $p[0], // 2 digits
        ];

        return $plateArray;
    }

    private function _plateFromCode($plate)
    {
        try {
            preg_match('~^(\d{2})(\d{2})(\d{2})(\d{3})$~', $plate, $matches);
            array_shift($matches);
            $plate = $matches[2] . ' ' . $this->_charFromCode($matches[1]) . ' ' . $matches [3] . ' - ' . $matches[0] .  ' ایران';
        } catch (\Exception $e) {
        }
        return $plate;
    }

    private function _charFromCode($code)
    {
        try {
            $charCods = array_flip(config('plate.char_codes'));
            return $charCods[$code];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function _drawAllChars($color)
    {
        $data = $this->getparsedData();
        $char = mb_strlen($data['char']) == 3 ? mb_substr($data['char'], 0, 1) : $data['char'];
        $string = $data['2DigitNumber'] . $char . $data['3DigitNumber'];

        $margins = [[-4, -12], [4, -12], [2, -7], [5, -11], [4, -10], [2, -10], [3, -10], [0, -10], [3, -10], [2, -11]];
        $charMargin = [[8, 4], [-2, 4], [-6, 2], [-2, 3], [-3, 0], [3, 4], [-2, 4], [0, 0], [3, 2], [-2, 4]];

        $font = $this->_resourcePath . 'IranPlateFont-Regular.ttf';

        $fontSize = 48 - 5;

        $Y = 27;
        $yChar = $Y - 1;
        $x = $fontSize + 1;

        $chars = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $index => $char) {
            $y = is_numeric($char) ? $Y : $yChar;

            if (isset($margins[$char])) {
                $margin = $margins[$char];
            } else {
                $margin[0] = isset($chars[$index - 1]) ? $charMargin[$chars[$index - 1]][0] : 0;
                $margin[1] = isset($chars[$index + 1]) ? $charMargin[$chars[$index + 1]][1] : 0;
                $margin[0] += 12;
                $margin[1] += 12;
            }

            $x += $margin[0] + 2;
            $this->_drawChar($char, $font, $fontSize, $color, $x, $y);
            $x += $margin[1] + 2 + $fontSize;
        }

        $chars = preg_split('//u', $data['stateNumber'], -1, PREG_SPLIT_NO_EMPTY);
        $x = 334;
        $y += 13;
        $fontSize -= 4;
        $margins = [[0, 0], [15, -19], [8, -11], [5, -9], [5, -9], [4, -7], [5, -6], [5, -7], [6, -9], [5, -8]];
        foreach ($chars as $char) {
            $margin = $margins[$char];
            $x += $margin[0];
            $this->_drawChar($char, $font, $fontSize, $color, $x, $y);
            $x += $margin[1] + $fontSize;
        }
    }

    private function _drawChar($text, $font, $fontSize, $color, $x = 0, $y = 0)
    {
        $colors = [$color, 80, 200, 180];
        $count = count($colors);
        $text = $this->_convertPersianNumber($text);
        $y += $fontSize;

        foreach (array_reverse($colors) as $index => $color) {
            $color = \imagecolorallocate($this->_image, $color, $color, $color);
            $xx = $x - $index * .2;
            $yy = $y + $index * .2;
            $ff = $fontSize + ($count - $index * .7);

            \imagettftext($this->_image, $ff, 0, $xx, $yy, $color, $font, $text);
        }
    }

    private function _convertPersianNumber($string)
    {
        $eng = range(0, 9);
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return str_replace($eng, $persian, $string);
    }

    private function _getImageNameBasedOnChar()
    {
        $groups = [
            'taxi' => ['ت', 'ع'],
            'gov' => ['الف'],
            'police' => ['پ', 'ث'],
        ];
        $char = $this->_parsed['char'];
        foreach ($groups as $image => $chars) {
            if (in_array($char, $chars)) {
                return 'plate-' . $image . '.png';
            }
        }
        return 'plate-normal.png';
    }

    private function _getColorNameBasedOnChar()
    {
        $groups = [
            240 => ['الف', 'پ', 'ث'],
        ];
        $char = $this->_parsed['char'];
        foreach ($groups as $color => $chars) {
            if (in_array($char, $chars)) {
                return $color;
            }
        }
        return 10;
    }

    private function _drawEnglishPlate()
    {
        $font = $this->_resourcePath . 'font.ttf';
        $textcolor = \imagecolorallocate($this->_image, 150, 150, 150);
        $text = $this->getInEnglish();

        \imagettftext($this->_image, 9, 0, 125, 78, $textcolor, $font, $text);

        if (!empty($this->_date)) {
            \imagettftext($this->_image, 9, 0, 282, 79, $textcolor, $font, $this->_date);
        }
    }

    public function getInEnglish($withIR = false)
    {
        $eng = $this->_engChars[$this->_parsed['char']];
        $text = ($withIR ? 'IR' : '') . $this->_parsed['2DigitNumber'] . $eng . $this->_parsed['3DigitNumber'] . '-' . $this->_parsed['stateNumber'];
        return $text;
    }
}
