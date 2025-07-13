<?php

namespace CustomFileSystem;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use CustomFileSystem\Helpers\Helpers;

abstract class CustomFileUploader extends CustomFileHandler
{
 
    protected array $file_path_uploadedFile_pairs = [];

    /**
     * @param Request|array $data
     * @return array
     */
    protected function getRequestData(Request | array $data): array
    {
        return $data instanceof Request ? $data->all() : $data;
    }

    /**
     * @param Request|array $data
     * @param string $fileKey
     * @return array|UploadedFile
     */
    protected function getFileFromDataArray(Request | array $data, string $fileKey): array | UploadedFile
    {
        $data = $this->getRequestData($data);
        if (!isset($data[$fileKey]))
        {
            $exceptionClass = Helpers::getExceptionClass();
            throw  new $exceptionClass($fileKey . "'s File Key Is Not Found In The Data Array");
        }
        return $data[$fileKey];
    }

    public function HasExtensionChanged(UploadedFile $file, string $fileOldName): bool
    {
        return $file->getClientOriginalExtension() !== File::extension($fileOldName);
    }

    public function getFileHashName(UploadedFile $file, string $folderName = ""): string
    {
        return $file->hashName($folderName);
    }

    public function getUploadedFileOriginalName(UploadedFile $file): string
    {
        return $file->getClientOriginalName();
    }
    public function getUploadedFileSize(UploadedFile $file): string
    {
        return $file->getSize();
    }
    public function getUploadedFileMimeType(UploadedFile $file): string
    {
        return $file->getClientMimeType();
    }

    //Make File Object ready to save with its path
    // (It can be used to make files ready to upload when file path is set .... use it for updating operation)
    public function makeFileReadyToStore(string $filePath, UploadedFile $file): bool
    {
        $this->file_path_uploadedFile_pairs[$filePath] = $file;
        return true;
    }

    /**
     * @param Request|array $data
     * @param string $fileKey
     * @param string $fileFolder
     * @param array $filePaths
     * @param bool $JsonResult
     * @return array
     * @throws Exception
     */
    public function processMultiUploadedFile(Request | array $data, string $fileKey, string $fileFolder, array $filePaths = [], bool $JsonResult = false): array
    {
        $files = $this->getFileFromDataArray($data, $fileKey);
        if (!is_array($files)) {
            $exceptionClass = Helpers::getExceptionClass();
            throw new $exceptionClass(str_replace("_", " ", $fileKey) . " Must Be An Array Of Files");
        }

        //If File Is really an array of files
        $files_path_array = [];
        foreach ($files as $index =>  $file) {
            /** @var UploadedFile $file */
            $file_new_path = $filePaths[$index] ?? $this->getFileHashName($file, $fileFolder);
            $files_path_array[] = $file_new_path;

            //Make Object ready to save with its path
            $this->makeFileReadyToStore($file_new_path, $file);
        }
        if ($JsonResult) {
            $files_path_array = json_encode($files_path_array);
        }
        $data[$fileKey] = $files_path_array;
        return $data;
    }

    /**
     * @param Request|array $data
     * @param string $fileKey
     * @param string $fileFolder
     * @param string $filePath
     * @return array
     * @throws Exception
     */
    //Will return the data array after editing it .... Or an Exception if an error is thrown
    public function processFile(Request | array $data, string $fileKey, string $fileFolder, string $filePath = ""): array
    {
        //Getting UploadedFile Object From data array
        $uploadedFileOb = $this->getFileFromDataArray($data, $fileKey);

        //Getting File Hashed Name To send it to DB
        //Getting File With Function To check its type ... since InvalidArgumentException object will be thrown if it is not UploadedFile Object
        /**  @var UploadedFile $uploadedFileOb */
        $file_new_path = $filePath ?? $this->getFileHashName($uploadedFileOb, $fileFolder);

        //Make File Object ready to save with its path
        $this->makeFileReadyToStore($file_new_path, $uploadedFileOb);

        //returning data array
        $data[$fileKey] = $file_new_path;
        return $data;
    }


    /**
     * @param string $file_path
     * @param $uploadedFile
     * @return string
     * @throws Exception
     */
    protected function uploadSingleFile(string $file_path,  $uploadedFile = null): string
    {
        if (!$uploadedFile instanceof UploadedFile)
        {
            $exceptionClass = Helpers::getExceptionClass();
            throw new $exceptionClass("Expected UploadedFile Object For '$uploadedFile' Parameter , null given !.");
        }
        return $uploadedFile->storeAs("/", $file_path, $this->disk);
    }


    protected function restartUploader(): bool
    {
        $this->file_path_uploadedFile_pairs = [];
        return true;
    }

    /**
     * @param array $file_path_uploadedFile_pairs
     * @return bool
     * @throws Exception
     */
    public function uploadFiles(array $file_path_uploadedFile_pairs = []): bool
    {
        if (empty($file_path_uploadedFile_pairs)) {
            $file_path_uploadedFile_pairs = $this->file_path_uploadedFile_pairs;
        }
        foreach ($file_path_uploadedFile_pairs as $file_path => $uploadedFile) {
            $this->uploadSingleFile($file_path, $uploadedFile);
        }
        //No matter if the $file_path_uploadedFile_pairs is empty or not .... if the execution come to this line the function get successful
        return $this->restartUploader();
    }

    /**
     * @param string $fileKey
     * @param string $folderPath
     * @return string
     * @throws Exception
     * return fake file's path and set it ready to uploading later when use uploadFiles Method
     */
    public function fakeSingleFile(string $fileKey , string $folderPath) : string
    {
        $files[$fileKey] = UploadedFile::fake()->image($fileKey);
        return $this->processFile($files ,$fileKey , $folderPath )[$fileKey];
    }

    /**
     * @param string $filesKey
     * @param string $folderPath
     * @param int $length
     * @return array
     * @throws Exception
     */
    public function fakeMultiFiles(string $filesKey , string $folderPath , int $length = 2) : array
    {
        $files = [];
        for ($i=1;$i<=$length;$i++)
        {
            $files[$filesKey][] = UploadedFile::fake()->image($filesKey . "_" . $i);
        }
        return $this->processMultiUploadedFile($files , $filesKey , $folderPath )[$filesKey];
    }


}
