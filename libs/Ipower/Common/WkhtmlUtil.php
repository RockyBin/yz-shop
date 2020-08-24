<?php

namespace Ipower\Common;
class WkhtmlUtil
{
    /** @var string wkhtml 这个命令行的路径 */
    private $wkhtmltoimage;

    public function __construct()
    {
        /*
        在 centos 下安装 wkhtmltoimage ，需要下载相应的 centos6 或 centos7 版本的 rpm ，并且要先安装 xorg-x11-fonts-75dpi 这个工具
        在 windows 服务器下安装，要先安装好相应版本
        需要在laravel配置文件中填写 wkhtmltoimage 程序的完整路径
        */
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') $this->wkhtmltoimage = config('app.WKHTMLTOIMAGE_PATH_WIN');
        else $this->wkhtmltoimage = config('app.WKHTMLTOIMAGE_PATH_LINUX');
    }

    /**
     * @param string $input 可以是一个网址，或一个本地的 HTML 文件路径
     * @param string $output 生成的图片保存到哪里
     * @param array $options 生成图片的大小，类型等参数
     * @return boolean 生成成功就返回true，否则为false
     */
    public function generateImg(string $input, string $output, array $options = array())
    {
        $format = $options['format'] ?? 'jpg';
        $cmd = $this->wkhtmltoimage . " --format " . $format;
        if ($options['width']) $cmd .= " --width " . $options['width'];
        if ($options['height']) $cmd .= " --height " . $options['height'];
        $cmd .= " \"" . $input . "\" " . $output;
        @unlink($output);
        shell_exec($cmd);
        return file_exists($output);
    }
}