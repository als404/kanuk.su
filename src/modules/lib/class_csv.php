<?php
class CSV {
	private $_csv_file = null;

	// @param string $csv_file - путь до csv-файла
	public function __construct($csv_file) {
		if (file_exists($csv_file)) { //если файл существуем
			$this->_csv_file = $csv_file; //записываем путь к файлу в переменную
		} else { //если файл не найден вызываем исключение
			throw new Exception("Файл $csv_file не найден");
		}
	}

	// @return array; массив с даными из CSV
	public function getCSV() {
		$handle = fopen($this->_csv_file, "r"); //открываем CSV для чтения
		$array_line_full = array(); //массив в котором будут храниться даные из CSV
		//проходим весть CSV, и читаем построчно.
		while(($line = fgetcsv($handle, 0, ";")) !== FALSE){
			$array_line_full[] = $line;
		}
		fclose($handle); //закрываем файл
		return $array_line_full; //возвращаем прочтенные данные
	}
}