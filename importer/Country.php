<?php
namespace kalyabin\geonames\importer;

use Exception;

/**
 * Класс для скачивания и импорта стран из http://download.geonames.org/export/dump/
 *
 * В качестве адреса, по которому расположена БД стран используется URL CSV-файла (по умолчанию http://download.geonames.org/export/dump/countryInfo.txt).
 * Хранится в переменной $downloadUrl.
 *
 * Пример использования:
 * ```php
 * $importer = new Country('/tmp/', function($country) {
 *     fwrite(STDOUT, "Consume country: ");
 *     print_r($country);
 *     fwrite(STDOUT, "\n");
 * });
 * $importer->process();
 * ```
 */
class Country
{
    /**
     * Количество колонок в CSV
     */
    const COLUMNS_COUNT = 19;

    /**
     * @var string базовый URL для скачивания стран в формате CSV
     */
    public $downloadUrl = 'http://download.geonames.org/export/dump/countryInfo.txt';

    /**
     * @var string разделитель - по умолчанию - знак табуляции
     */
    public $columnsSeparator = "\t";

    /**
     * @var string разделитель колонок
     */
    public $columnsEnclosure = '"';

    /**
     * @var string экранирующий символ
     */
    public $escape = "\\";

    /**
     * @var array массив названия колонок
     */
    protected $columns = [];

    /**
     * @var string путь к файлу, который будет сохранен на диске
     */
    protected $downloadedFilePath;

    /**
     * @var callable консумер строк
     */
    protected $rowConsumer;

    /**
     * Конструктор
     *
     * @param string $downloadPath путь к папке, в которую скачать файл из downloadUrl
     * @param callable $rowConsumer консумер строк CSV. Пример консумера:
     * ```php
     * function($country) {
     *     print 'Consume country: ' . print_r($country) . "\n";
     *     // do something else
     * }
     * ```
     * @throws Exception если не существует папка для скачивания, либо не правильно передан консумер
     */
    public function __construct($downloadPath, $rowConsumer)
    {
        if (!is_string($downloadPath) || !is_dir($downloadPath)) {
            throw new Exception('Path ' . $downloadPath . ' not exists');
        }

        if (!is_callable($rowConsumer)) {
            throw new Exception('Wrong row consumer');
        }

        $this->downloadedFilePath = $downloadPath . DIRECTORY_SEPARATOR . md5($this->downloadUrl . time()) . '.csv';
        $this->rowConsumer = $rowConsumer;
    }

    /**
     * Скачать файл по URL downloadUrl
     *
     * @return boolean
     */
    protected function downloadCsv()
    {
        $fh = fopen($this->downloadedFilePath, 'wb');
        try {
            $ch = curl_init($this->downloadUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_FILE, $fh);
            curl_exec($ch);
            curl_close($ch);
            fclose($fh);
        }
        catch (Exception $ex) {
            if (is_resource($fh)) {
                fclose($fh);
            }
            throw $ex;
        }

        if (is_file($this->downloadedFilePath)) {
            return true;
        }

        return false;
    }

    /**
     * Удалить скаченный файл
     */
    protected function removeDownloadedFile()
    {
        if (is_file($this->downloadedFilePath)) {
            unlink($this->downloadedFilePath);
        }
    }

    /**
     * Проверить, что строка $row является строкой, определяющей колонки.
     * Если эта строка определяет колонки, то устанавливает массив columns и возвращает true,
     * иначе возвращает false.
     *
     * @param array $row входная строка
     * @return boolean
     */
    protected function checkIsColumnDifenition($row)
    {
        $ret = false;

        // если отсутсвует определение колонок - значит вполне возможно эта строка и есть определяющая колонки
        if (empty($this->columns) && count($row) == self::COLUMNS_COUNT) {
            $ret = true;
            foreach ($row as $k => $column) {
                if (!trim($column)) {
                    // колонка пустая
                    // строка, определяющая колонки не должна состоять из пустых колонок
                    $ret = false;
                    break;
                }
            }
        }

        if ($ret) {
            // запомнить название колонок
            $this->columns = $row;
        }

        return $ret;
    }

    /**
     * Возвращает true, если определены колонки
     *
     * @param array $row
     * @return boolean
     */
    protected function checkIsRow($row)
    {
        if (!empty($this->columns) && count($row) == self::COLUMNS_COUNT) {
            return true;
        }
        return false;
    }

    /**
     * Запустить консумер для строки
     * @param array $row
     */
    protected function consumeRow($row)
    {
        if (!empty($row) && !empty($this->columns) && count($row) == count($this->columns)) {
            $country = array_combine($this->columns, $row);
            call_user_func_array($this->rowConsumer, [$country]);
        }
    }

    /**
     * Парсинг файла
     */
    protected function parseFile()
    {
        $fh = fopen($this->downloadedFilePath, 'r');
        try {
            while ($row = fgetcsv($fh, 0, $this->columnsSeparator, $this->columnsEnclosure, $this->escape)) {
                if (!$this->checkIsColumnDifenition($row) && $this->checkIsRow($row)) {
                    $this->consumeRow($row);
                }
            }
        }
        catch (Exception $ex) {
            if (is_resource($fh)) {
                fclose($fh);
            }
            throw $ex;
        }
        fclose($fh);
    }

    /**
     * Процесс обработки файла
     */
    public function process()
    {
        fwrite(STDOUT, "Download file from {$this->downloadUrl}: ");
        if ($this->downloadCsv()) {
            fwrite(STDOUT, "done\n");
        }
        else {
            fwrite(STDOUT, "error\n");
            $this->removeDownloadedFile();
            return;
        }

        fwrite(STDOUT, "Begin parse file {$this->downloadedFilePath}...\n");
        $this->parseFile();
        fwrite(STDOUT, "End parse file {$this->downloadedFilePath}...\n");

        fwrite(STDOUT, "Remove temporary file: ");
        $this->removeDownloadedFile();
        fwrite(STDOUT, "done\n");
    }
}
