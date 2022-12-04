<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Vilnius');

# 1. Parsisiųsti failą iš https://www.omniva.ee/locations.json
#   1.1 Patikrint connection'a? (panaudojant cURL) ??
#   1.2 Patikrinti ar gauti duomenys.
#   1.3 Gautą rezultatą išsaugom log faile (sėkmės ir nesėkmės atveju).
#   1.4 Sėkmės atveju atliekam sekančius žingsius.
# 2. Duomenis išsaugom masyve.
#   2.1 Atsifiltruojam tik reikalingus duomenis: (TYPE = 0, A0_NAME = LT).
#   2.2 Paruoštus duomenis išsaugome json faile, kuris bus naudojamas puslapio reikmėms.
#       * Jeigu failo atnaujinimas nepavyko (1.), į failą nesaugom ir naudojam neatnaujintą failo versiją.
# 6. Failo parsisiuntimo ir atnaujinimo skriptą įdėti į serverio daily cron job.

class OmnivaDownloadExceptions extends Exception
{
}

class OmnivaSaveFileExceptions extends Exception
{
}


const OMNIVA_FILE_URL = 'https://www.omniva.ee/locations.json';
const OMNIVA_FILE_PATH = '/var/www/miestomedus.tk/omnivaLocations.json';
const OMNIVA_LOG_FILE = '/var/www/miestomedus.tk/omnivaLocationDownload.log';

/**
 * @throws OmnivaDownloadExceptions
 */

function checkURL(): bool
{
    $ch = curl_init(OMNIVA_FILE_URL);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode === 200) {
        return true;
    } else {
        throw new OmnivaDownloadExceptions("Connection error. Can't download " . OMNIVA_FILE_URL);
    }
}

/**
 * @throws OmnivaDownloadExceptions
 */
function getOmnivaFile(): array
{
    if (!checkURL()) {
        die;
    }
    if (!file_get_contents(OMNIVA_FILE_URL)) {
        throw new OmnivaDownloadExceptions('"' . OMNIVA_FILE_URL . '" download error.');
    }
    return json_decode(file_get_contents(OMNIVA_FILE_URL), true);
}

function filterOmnivaLocations(array $omnivaLocations): array
{
    $filteredOmnivaLocations = [];
    foreach ($omnivaLocations as $location) {
        if ($location['A0_NAME'] === "LT" && $location['TYPE'] == 0) {
            $filteredOmnivaLocations[] = $location;
        }
    }
    return $filteredOmnivaLocations;
}

function writeLogToFile($logFilePath, string $message): void
{
    $stream = fopen($logFilePath, 'a');
    fwrite($stream, $message);
    fclose($stream);
}

/**
 * @throws OmnivaSaveFileExceptions
 */
function saveOmnivaLocations(array $filteredOmnivaLocations): int
{
    fopen(OMNIVA_FILE_PATH, 'w');
    if (!file_put_contents(OMNIVA_FILE_PATH, json_encode($filteredOmnivaLocations, JSON_PRETTY_PRINT))) {
        throw new OmnivaSaveFileExceptions('Error occured while saving file');
    }
    return file_put_contents(OMNIVA_FILE_PATH, json_encode($filteredOmnivaLocations, JSON_PRETTY_PRINT));
}

try {

    $successMsg = '@ ' . date_format(date_create(), 'Y-m-d H:i:s') . ' File download complete from: ' . OMNIVA_FILE_URL . PHP_EOL;
    writeLogToFile(OMNIVA_LOG_FILE, $successMsg);
    echo $successMsg . PHP_EOL;
    try {
        saveOmnivaLocations(filterOmnivaLocations(getOmnivaFile()));
        $successMsg = '@ ' . date_format(date_create(), 'Y-m-d H:i:s') . ' Filtered Omniva locations saved to: ' . OMNIVA_LOG_FILE . PHP_EOL;
        writeLogToFile(OMNIVA_LOG_FILE, $successMsg);
        echo $successMsg . PHP_EOL;
    } catch (OmnivaSaveFileExceptions $e) {
        $errorMsg = '@ ' . date_format(date_create(), 'Y-m-d H:i:s') . ' Error mesage: ' . $e->getMessage() . PHP_EOL;
        writeLogToFile(OMNIVA_LOG_FILE, $errorMsg);
        echo $errorMsg . PHP_EOL;
    }
} catch (OmnivaDownloadExceptions $e) {
    $errorMsg = '@ ' . date_format(date_create(), 'Y-m-d H:i:s') . ' Error mesage: ' . $e->getMessage() . PHP_EOL;
    writeLogToFile(OMNIVA_LOG_FILE, $errorMsg);
    echo $errorMsg . PHP_EOL;
}


die();