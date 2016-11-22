<?php
namespace Plate;

use Plate\Exception\CharIsNotValid;
use Plate\Exception\StateIsNotValid;
use Plate\Exception\CityNotFound;
use Plate\Exception\PlateIsNotValid;

class Plate{
	private $_plate = null;
	private $_parsed = null;
	private $_data = null;
	private $_suportedChars = null;


	public function __construct(){
		$config = config('plate');
		$this->_data = $config['state_data'];
		$this->_suportedChars = $config['supported_chars'];
	}

	private function parse(){
		preg_match($this->getRegEx(), $this->_plate, $matchs);

		$stateNumber = $matchs[4];
		$stateName = $this->_getStateNameByNumber($stateNumber);
		$character = $matchs[2];

		$this->_parsed = [
			'cityName'			=>	$this->_getCityNameByCharAndNumber($stateName, $character, $stateNumber),
			'type'				=>	$this->_getTypeByChar($character),
			'char'				=>	$character,
			'2DigitNumber'		=>	$matchs[1],
			'3DigitNumber'		=>	$matchs[3],
			'countryName'		=>	$matchs[5],
			'stateNumber'		=>	$stateNumber,
			'stateName'			=>	$stateName,
		];
	}

	private function _getTypeByChar($char){
		if (!array_key_exists($char, $this->_suportedChars)) {
			throw new CharIsNotValid("This Char Is Not Valid");
		}

		return $this->_suportedChars[$char];
	}

	private function _getStateNameByNumber($number){
		foreach ($this->_data as $stateName => $numbers) {
			if (array_key_exists($number, $numbers)) {
				return $stateName;
			}
		}

		throw new StateIsNotValid("There Is Not Any State With This Number");
	}

	private function _getCityNameByCharAndNumber($state, $char, $number){
		if(empty($this->_data[$state][$number][$char][0])){
			return '';
			throw new CityNotFound("There Is Not Any City With This Information");
		}

		return implode(', ', $this->_data[$state][$number][$char]);
	}

	public function setPlate($plate){
		$this->_plate = $plate;
		$this->validate();
		$this->parse();
		return $this;
	}

	public function getRegEx(){
		$farsiChars = implode('|', array_keys($this->_suportedChars));
		return "/([1-9]\d{1})\s+({$farsiChars})\s+([1-9]\d{2})\s+\-\s+([1-9]\d{1})\s+(ایران)/";
	}

	public function validate($plate = null, $softCheck = false){
		if (empty($plate)){
			$plate = $this->_plate;
		}

		preg_match($this->getRegEx(), $plate, $matchs);

		if (count($matchs) !== 6) {
			if($softCheck) {
				return false;
			} else {
				throw new PlateIsNotValid("Plate Number Is Not Valid");
			}
		}
	}

	public function getparsedData(){
		return $this->_parsed;
	}

	public function getCountry(){
		return $this->_parsed['countryName'];
	}

	public function getCity(){
		return $this->_parsed['cityName'];
	}

	public function getState(){
		return $this->_parsed['stateName'];
	}

	public function getType(){
		return $this->_parsed['type'];
	}

	public function getStateNumber(){
		return $this->_parsed['stateNumber'];
	}

	public function get2DigitNumber(){
		return $this->_parsed['2DigitNumber'];
	}

	public function get3DigitNumber(){
		return $this->_parsed['3DigitNumber'];
	}

	public function isCab(){
		return $this->_parsed['type'] === 'تاکسی' ? true : false;
	}

	public function getImage($exportPath){
		$data = $this->getparsedData();
		$resourcePath = __DIR__ . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR;
		$imageName = $this->isCab() ? 'plate-taxi' : 'plate-normal';
		$im = imagecreatefrompng($resourcePath . $imageName . '.png');
		$font = $resourcePath . 'IranPlateFont-Regular.ttf';
		$color = 20;

		$fontSize = 44;
		$Y = 22;
		$yChar = $Y - 1;

		$char = mb_strlen($data['char'])==3 ? mb_substr($data['char'], 0,1) : $data['char'];
		$string = $data['2DigitNumber'] . $char . $data['3DigitNumber'];
		$chars = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);


		$margins = [[-4, -12], [4, -12], [2, -7], [2, -11], [2, -10], [1, -10], [3, -10], [2, -10], [0, -10], [2, -11]];
		$charMargin = [[8, 4], [-2, 4], [-9, 2], [-2, 3], [-3, 0], [2, 4], [-7, 4], [0, 0], [6, 4], [-2, 4]];
		$x =  $fontSize - 8;
		foreach ($chars as $index=>$char) {
			$y = is_numeric($char) ? $Y : $yChar;
			
			if (isset($margins[$char])) {
				$margin = $margins[$char];
			}
			else{
				$margin[0] = isset($chars[$index-1]) ? $charMargin[$chars[$index-1]][0] : 0;
				$margin[1] = isset($chars[$index+1]) ? $charMargin[$chars[$index+1]][1] : 0;
				$margin[0] += 2;
				$margin[1] += 10;
			}

			$x += $margin[0];
			$this->_gdDrawText($char, $im, $font, $fontSize, $color, $x, $y);
			$x += $margin[1] + $fontSize;
		}
		
		$chars = preg_split('//u', $data['stateNumber'], -1, PREG_SPLIT_NO_EMPTY);
		$x = 281;
		$fontSize -= 7;
		foreach ($chars as $char) {
			$margin = $margins[$char];
			$x+= $margin[0]  + 3;
			$this->_gdDrawText($char, $im, $font, $fontSize, $color, $x, $y + 10);
			$x+= $margin[1] + 1  + $fontSize;
		}

		imagepng($im, $exportPath);
		imagedestroy($im);
	}

	private function _gdDrawText($text, $im, $font, $fontSize, $colorNo, $x=0, $y=0){
		$text = $this->_convertPersianNumber($text);
		$y += $fontSize;
		$color = imagecolorallocate($im, $colorNo, $colorNo, $colorNo);
		$colorNo += 200;
		$gray1 = imagecolorallocate($im, $colorNo, $colorNo, $colorNo);
		$colorNo -= 110;
		$gray2 = imagecolorallocate($im, $colorNo, $colorNo, $colorNo);

		imagettftext($im, $fontSize + 3, 0, $x - 1, $y + 2 , $gray2, $font, $text);
		imagettftext($im, $fontSize + 1, 0, $x - 1, $y , $gray1, $font, $text);
		imagettftext($im, $fontSize, 0, $x, $y , $color, $font, $text);
	}

	private function _convertPersianNumber($string){
	    $eng = range(0, 9);
	    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
	    return str_replace($eng, $persian, $string);
	}
}