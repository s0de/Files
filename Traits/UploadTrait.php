<?php
/**
 *
 *
 * All rights reserved.
 *
 * @author Okulov Anton
 * @email qantus@mail.ru
 * @version 1.0
 * @company HashStudio
 * @site http://hashstudio.ru
 * @date 18/11/16 09:48
 */

namespace Modules\Files\Traits;

use Phact\Components\PathInterface;
use Phact\Main\Phact;

trait UploadTrait
{
    public $ds = DIRECTORY_SEPARATOR;

    public $tempDir;

    /**
     * Uploading data
     */
    public function upload()
    {
        if (!$this->tempDir) {
            /** @var $paths PathInterface */
            if (($app = Phact::app()) && ($paths = $app->getComponent(PathInterface::class))) {
                $this->tempDir = $paths->get('www') . $this->ds . 'temp';
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $temp_dir = $this->tempDir . $this->ds . $_GET['flowIdentifier'];
            $chunk_file = $temp_dir . $this->ds . $_GET['flowFilename'] . '.part' . $_GET['flowChunkNumber'];
            if (file_exists($chunk_file)) {
                header("HTTP/1.0 200 Ok");
            } else {
                header("HTTP/1.0 404 Not Found");
            }
        }

        if (!empty($_FILES)) foreach ($_FILES as $file) {

            // check the error status
            if ($file['error'] != 0) {
                continue;
            }

            // init the destination file (format <filename.ext>.part<#chunk>
            // the file is stored in a temporary directory
            $temp_dir = $this->tempDir . '/' . $_POST['flowIdentifier'];
            $dest_file = $temp_dir . '/' . $_POST['flowFilename'] . '.part' . $_POST['flowChunkNumber'];

            // create the temporary directory
            if (!is_dir($temp_dir)) {
                mkdir($temp_dir, 0777, true);
            }

            // move the temporary file
            if (move_uploaded_file($file['tmp_name'], $dest_file)) {
                // check if all the parts present, and create the final destination file
                $name = $_POST['flowFilename'];
                $fileName = implode($this->ds, [$this->tempDir, $name]);
                $this->createFileFromChunks($temp_dir, $_POST['flowFilename'], $_POST['flowChunkSize'], $_POST['flowTotalSize'], $fileName);
            }
        }
    }

    /**
     * Recursive delete directory
     * @param $dir
     * @return bool|null
     */
    function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . $this->ds . $object) == "dir") {
                        $this->rrmdir($dir . $this->ds . $object);
                    } else {
                        unlink($dir . $this->ds . $object);
                    }
                }
            }
            reset($objects);
            return rmdir($dir);
        }
        return null;
    }

    /**
     * Create file from chunks
     * @param $temp_dir
     * @param $fileName
     * @param $chunkSize
     * @param $totalSize
     * @param $finalDestination
     * @return bool
     */
    function createFileFromChunks($temp_dir, $fileName, $chunkSize, $totalSize, $finalDestination)
    {
        // count all the parts of this file
        $total_files = 0;
        foreach (scandir($temp_dir) as $file) {
            if (stripos($file, $fileName) !== false) {
                $total_files++;
            }
        }

        // check that all the parts are present
        // the size of the last part is between chunkSize and 2*$chunkSize
        if ($total_files * $chunkSize >= ($totalSize - $chunkSize + 1)) {

            // create the final destination file
            if (($fp = fopen($finalDestination, 'w')) !== false) {
                for ($i = 1; $i <= $total_files; $i++) {
                    fwrite($fp, file_get_contents($temp_dir . $this->ds . $fileName . '.part' . $i));
                }
                fclose($fp);

                $isValid = $this->validateFile($finalDestination);

                if ($isValid === true){
                    $this->saveModel($finalDestination);
                    try {
                        unlink($finalDestination);
                    } catch (\Exception $e) {

                    }
                }else{
                    echo json_encode([
                        'errors'=>[
                            'file'=> $finalDestination,
                            'error'=> $isValid
                        ],
                    ]);
                }
            } else {
                return false;
            }

            // rename the temporary directory (to avoid access from other
            // concurrent chunks uploads) and than delete it
            if (rename($temp_dir, $temp_dir . '_UNUSED')) {
                $this->rrmdir($temp_dir . '_UNUSED');
            } else {
                $this->rrmdir($temp_dir);
            }
        }
        return true;
    }

    public function validateFile($path)
    {
        return true;
    }

    public function saveModel($path)
    {
        return true;
    }
}