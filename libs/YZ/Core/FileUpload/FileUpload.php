<?php
/**
 * 文件上传处理
 */

namespace YZ\Core\FileUpload;


use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;
use YZ\Core\Site\Site;

class FileUpload
{
    protected $fileMaxSize = 0; // 文件最大多少 默认不限制
    protected $allowFileType = ["png", "jpg", "gif", 'jpeg']; //允许上传的文件类型 默认为图片的
    protected $saveDirectory = '';  // 文件保存目录
    protected $fileName = '';       // 文件名
    protected $fileExtension = '';  // 文件后缀
    protected $uploadType = 'image'; // 上传的是文件还是图片
    protected $file = null;          // 上传的文件
    protected $fileSize = 0;        // 文件大小

    /**
     * FileUpload constructor.
     * @param UploadedFile $file 要上传的文件
     * @param string $saveDirectory 要保存的路径 默认为站点默认文件路径
     * @param string $fileName 上传文件保存的name 默认为上传文件的原名称
     * @throws
     */
    public function __construct(UploadedFile $file, $saveDirectory = '', $fileName = '')
    {
        if (empty($file) || !$file->isValid()) {
            throw new \Exception('文件为空', 400);
        } else {
            $this->file = $file;
            // 文件后缀
            $this->fileExtension = $file->getClientOriginalExtension();
            // 文件名  默认为上传的原始名称
            $this->fileName = $fileName ?: pathinfo($file->getClientOriginalName())['filename'];
            $this->fileSize = $file->getClientSize();
            if (!$this->fileSize) {
                throw new \Exception('文件大小为0', 400);
            }
            // 要保存的路径
            $this->setSaveDirectory($saveDirectory);
            // 上传类型
            $this->uploadType = !exif_imagetype($this->file->getRealPath()) ? 'file' : 'image';
        }
    }

    /**
     * 设置允许上传的文件类型
     * @param array $type
     */
    public function setAllowFileType($type = [])
    {
        $this->allowFileType = $type;
    }

    /**
     * 设置允许上传文件的最大值
     * @param int $size
     */
    public function setFileMaxSize($size = 0)
    {
        $this->fileMaxSize = $size;
    }

    /**
     * 设置保存的路径
     * @param string $path
     */
    public function setSaveDirectory($path = '')
    {
        $this->saveDirectory = $path ?: Site::getSiteComdataDir('', true);
    }

    /**
     * 获取保存路径
     * @return string
     */
    public function getSaveDirectory()
    {
        return $this->saveDirectory;
    }

    /**
     * 设置保存的文件名称
     * @param string $fileName
     */
    public function setFileName($fileName = '')
    {
        $this->fileName = $fileName ?: $this->fileName;
    }

    /**
     * 获取带后缀的文件名
     * @return string
     */
    public function getFullFileName()
    {
        return $this->fileName . '.' . $this->fileExtension;
    }
    public function getFileExtension()
    {
        return $this->fileExtension;
    }

    /**
     * 检测是否可以保存
     * @return bool
     * @throws \Exception
     */
    public function check()
    {
        // 文件是否太大
        if ($this->fileMaxSize > 0 && $this->fileSize > $this->fileMaxSize) {
            throw new \Exception( '文件不能超过' . $this->fileMaxSize/1024 . 'Mb');
        }
        // 类型是否允许上传 转为小写比对
        if (!in_array(strtolower($this->fileExtension), array_map('strtolower', $this->allowFileType))) {
            throw new \Exception('文件类型错误');
        }
        return true;
    }

    /**
     * 裁剪图片
     * @param int $maxWidth                     图片最大宽度
     * @param string $saveImageName             裁剪后保存的图片名称
     * @param int $quality                      质量 默认85
     * @param null $maxHeight                   最大高度
     * @return bool|\Intervention\Image\Image
     * @throws \Exception
     */
    public function reduceImageSize($maxWidth, $saveImageName = '', $quality = 85, $maxHeight = null)
    {
        if ($this->uploadType != 'image') {
            throw new \Exception('不是图片，无法进行裁剪', 400);
        }
        $image = Image::make($this->file->getRealPath());
        // iOS拍照上传的图片 在HTML页面 会横屏显示 这里根据网上找的做一下图片旋转
        $exif = $image->exif();
        if ($exif['Orientation']) {
            switch ($exif['Orientation']) {
                case 8:
                    $image->rotate(90);
                    break;
                case 3:
                    $image->rotate(180);
                    break;
                case 6:
                    $image->rotate(-90);
                    break;
            }
        }
        $image->resize($maxWidth, $maxHeight, function ($img) {
            $img->aspectRatio(); // 等比例
            $img->upsize(); // 防止图片尺寸变大
        });
        $this->check();
        if (!file_exists($this->saveDirectory)) {
            File::makeDirectory($this->saveDirectory, 0777, true);
        }
        $this->setFileName($saveImageName);
        $saveImagePath = $this->saveDirectory . $this->fileName . '.' . $this->fileExtension;
        return $image->save($saveImagePath, $quality);
    }

    /**
     * @param string $fileName  要保存的文件名
     * @return \Symfony\Component\HttpFoundation\File\File
     * @throws \Exception
     */
    public function save($fileName = '')
    {
        $this->check();
        $this->setFileName($fileName);
        return $this->file->move($this->saveDirectory, $this->getFullFileName());
    }
}