<?php
namespace Ipower\Common;
/**
 * Zip文件操作类
 * Class Zip
 * @package Ipower\Common
 */
class Zip
{
    /**
     * 将文件添加到zip文件中
     * @param type $bassepath 基本目录，用来在 ZipArchive::addFile（） 生成localname时去除文件的基本路径
     * @param type $path 要添加到压缩文件的目录或文件
     * @param type $zip ZipArchive 对象
     */
    function addFileToZip($bassepath,$path, $zip) {
        $handler = opendir($path); //打开当前文件夹由$path指定。
        /*
          循环的读取文件夹下的所有文件和文件夹
          其中$filename = readdir($handler)是每次循环的时候将读取的文件名赋值给$filename，
          为了不陷于死循环，所以还要让$filename !== false。
          一定要用!==，因为如果某个文件名如果叫'0'，或者某些被系统认为是代表false，用!=就会停止循环
         */
        while (($filename = readdir($handler)) !== false) {
            if ($filename != "." && $filename != "..") {//文件夹文件名字为'.'和‘..’，不要对他们进行操作
                if (is_dir($path . "/" . $filename)) {// 如果读取的某个对象是文件夹，则递归
                    $this->addFileToZip($bassepath, $path . "/" . $filename, $zip);
                } else { //将文件加入zip对象
                    $filepath = $path . "/" . $filename;
                    $localname = str_replace($bassepath, '', $filepath);
                    if (substr($localname, 0, 1) == '\\' || substr($localname, 0, 1) == '/')
                        $localname = substr($localname, 1);
                    $zip->addFile($filepath, $localname);
                }
            }
        }
        @closedir($path);
    }

    /**
     * 压缩指定目录
     * @param $path 要压缩的目录路径
     * @param $zipfile 生成的压缩文件保存路径
     * @throws \Exception
     */
    function zipDir($path, $zipfile) {
        $zip = new \ZipArchive();
        if ($zip->open($zipfile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            $this->addFileToZip($path, $path, $zip); //调用方法，对要打包的根目录进行操作，并将ZipArchive的对象传递给方法
            $zip->close(); //关闭处理的zip文件
        }else{
			throw new \Exception("can not open zip file $zipfile");
		}
    }
}
