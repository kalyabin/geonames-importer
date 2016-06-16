<?php
namespace kalyabin\geonames\importer;

use Exception;
use ZipArchive;

/**
 * Импорт базы городов с сайта: http://download.geonames.org/export/dump/
 *
 * На входе в конструктор необходимо указать:
 * - временную папку, в которую необходимо скачать данные;
 * - название архива, в котором расположены города (например, RU.zip, cities15000.zip  и т.д.). Полный список архивов см. http://download.geonames.org/export/dump/
 * - консумер города.
 *
 * Пример использования:
 * ```php
 * $importer = new City('/tmp/', 'cities5000.zip', function($city) {
 *     fwrite(STDOUT, "Consume city: ");
 *     print_r($city);
 *     fwrite(STDOUT, "\n");
 * });
 * $importer->process();
 * ```
 */
class City
{
    /**
     * Количество колонок в CSV
     */
    const COLUMNS_COUNT = 19;

    /**
     * @var string базовый URL для скачивания городов в формате CSV
     */
    public $baseUrl = 'http://download.geonames.org/export/dump/';

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
     * @var string URL для скачивания
     */
    protected $downloadUrl;

    /**
     * @var array массив названия колонок
     */
    protected $columns = [
        'geonameid',
        'name',
        'asciiname',
        'alternatenames',
        'latitude',
        'longitude',
        'feature class',
        'feature code',
        'country code',
        'cc2',
        'admin1 code',
        'admin2 code',
        'admin3 code',
        'admin4 code',
        'population',
        'elevation',
        'dem',
        'timezone',
        'modification date',
    ];

    /**
     * @var string путь к файлу, который будет сохранен на диске
     */
    protected $downloadedFilePath;

    /**
     * @var string название файла внутри архива
     */
    protected $unpackCsvName;

    /**
     * @var string путь к CSV-файлу
     */
    protected $csvFilePath;

    /**
     * @var callable консумер строк
     */
    protected $rowConsumer;


    /**
     * Конструктор
     *
     * @param string $downloadPath путь к папке, в которую скачать файл
     * @param string $archiveName название архива
     * @param callable $rowConsumer консумер строк CSV. Пример консумера:
     * ```php
     * function($city) {
     *     print 'Consume city: ' . print_r($country) . "\n";
     *     // do something else
     * }
     * ```
     * @throws Exception если не существует папка для скачивания, либо не правильно передан консумер
     */
    public function __construct($downloadPath, $archiveName, $rowConsumer)
    {
        if (!is_string($downloadPath) || !is_dir($downloadPath)) {
            throw new Exception('Path ' . $downloadPath . ' not exists');
        }

        if (!is_string($archiveName) || !trim($archiveName)) {
            throw new Exception('Set archive name');
        }

        if (!is_callable($rowConsumer)) {
            throw new Exception('Wrong row consumer');
        }

        $fileInfo = pathinfo($archiveName);
        $this->unpackCsvName = $fileInfo['filename'] . '.txt';
        $this->downloadUrl = $this->baseUrl . $archiveName;
        $this->downloadedFilePath = $downloadPath . DIRECTORY_SEPARATOR . md5($this->downloadUrl . time()) . '.zip';
        $this->csvFilePath = $downloadPath . DIRECTORY_SEPARATOR . md5($this->downloadUrl . time()) . DIRECTORY_SEPARATOR . $this->unpackCsvName;
        $this->rowConsumer = $rowConsumer;
    }

    /**
     * Скачать зип архив и распаковать его в csvFilePath
     *
     * @return boolean
     */
    protected function processZip()
    {
        $fh = fopen($this->downloadedFilePath, 'wb');

        $ch = curl_init($this->downloadUrl);
        try {
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
            // распаковать архив
            $zip = new ZipArchive();
            if ($zip->open($this->downloadedFilePath) !== true) {
                // не удалось открыть файл
                $zip->close();
                return false;
            }
            if (!$zip->extractTo(dirname($this->csvFilePath), [$this->unpackCsvName])) {
                // не удалось извлечь искомый файл
                $zip->close();
                return false;
            }
            $zip->close();

            return is_file($this->csvFilePath);
        }

        return false;
    }

    /**
     * Удалить скаченный и распакованный файл
     */
    protected function removeDownloadedFile()
    {
        if (is_file($this->downloadedFilePath)) {
            unlink($this->downloadedFilePath);
        }
        if (is_file($this->csvFilePath)) {
            unlink($this->csvFilePath);
        }
        if (is_dir(dirname($this->csvFilePath))) {
            rmdir(dirname($this->csvFilePath));
        }
    }

    /**
     * Парсинг CSV-файла из переменной csvFilePath
     */
    protected function parseFile()
    {
        $fh = fopen($this->csvFilePath, 'r');
        try {
            while (($row = fgets($fh)) !== false) {
                $row = str_getcsv($row, $this->columnsSeparator, $this->columnsEnclosure, $this->escape);
                if (count($row) == count($this->columns) && count($row) == self::COLUMNS_COUNT) {
                    $city = array_combine($this->columns, $row);
                    call_user_func_array($this->rowConsumer, [$city]);
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
        if ($this->processZip()) {
            fwrite(STDOUT, "done\n");
        }
        else {
            fwrite(STDOUT, "error\n");
            $this->removeDownloadedFile();
            return;
        }

        fwrite(STDOUT, "Begin parse file {$this->csvFilePath}...\n");
        $this->parseFile();
        fwrite(STDOUT, "End parse file {$this->csvFilePath}...\n");

        fwrite(STDOUT, "Remove temporary file: ");
        $this->removeDownloadedFile();
        fwrite(STDOUT, "done\n");
    }
}
