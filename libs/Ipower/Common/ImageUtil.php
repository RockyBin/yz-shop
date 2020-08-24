<?

namespace Ipower\Common;
/**
 * 图片处理工具
 * Class ImageUtil
 * @package Ipower\Common
 */
class ImageUtil
{
    /**
     * 生成圆角图片
     * @param $image 图片路径
     * @param int $radius 圆角大小
     * @return bool|resource
     */
    public static function imageCreateCorners($image, $radius = 20)
    {
        $src = $image;
        $w = imagesx($src);
        $h = imagesy($src);
        # create corners
        $res = true;
        if ($res) {

            $q = 8; # change this if you want
            $radius *= $q;

            # find unique color
            do {
                $r = rand(0, 255);
                $g = rand(0, 255);
                $b = rand(0, 255);
            } while (imagecolorexact($src, $r, $g, $b) < 0);

            $nw = $w * $q;
            $nh = $h * $q;

            $img = imagecreatetruecolor($nw, $nh);
            $alphacolor = imagecolorallocatealpha($img, $r, $g, $b, 127);
            imagealphablending($img, false);
            imagesavealpha($img, true);
            imagefilledrectangle($img, 0, 0, $nw, $nh, $alphacolor);

            imagefill($img, 0, 0, $alphacolor);
            imagecopyresampled($img, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

            imagearc($img, $radius - 1, $radius - 1, $radius * 2, $radius * 2, 180, 270, $alphacolor);
            imagefilltoborder($img, 0, 0, $alphacolor, $alphacolor);
            imagearc($img, $nw - $radius, $radius - 1, $radius * 2, $radius * 2, 270, 0, $alphacolor);
            imagefilltoborder($img, $nw - 1, 0, $alphacolor, $alphacolor);
            imagearc($img, $radius - 1, $nh - $radius, $radius * 2, $radius * 2, 90, 180, $alphacolor);
            imagefilltoborder($img, 0, $nh - 1, $alphacolor, $alphacolor);
            imagearc($img, $nw - $radius, $nh - $radius, $radius * 2, $radius * 2, 0, 90, $alphacolor);
            imagefilltoborder($img, $nw - 1, $nh - 1, $alphacolor, $alphacolor);
            imagealphablending($img, true);
            imagecolortransparent($img, $alphacolor);

            # resize image down
            $dest = imagecreatetruecolor($w, $h);
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            imagefilledrectangle($dest, 0, 0, $w, $h, $alphacolor);
            imagecopyresampled($dest, $img, 0, 0, 0, 0, $w, $h, $nw, $nh);

            # output image
            $res = $dest;
            imagedestroy($src);
            imagedestroy($img);
        }

        return $res;
    }

    /*
     Version 1.0
     last modify 2006.02.17 by hsq
     此函数用来将大图转换为小图
     $source = 大图源文件
     $filename = 要保存的文件名
     $savepath = 保存路径
     $thumbnailsize = 小图的宽度大小，小图的高度根据宽度按比例算出
     $angle = 图片的旋转角度
     */

    /**
     * 生成图片缩略图
     * @param $source 源图路径
     * @param $savepath 缩略图保存路径
     * @param $thumbnailsize 缩略图大小（宽或高不超此值，如果图宽>图高，此为缩略图的宽度，如果图宽<图高，此为缩略图的高度）
     * @param int $angle 旋转多少度
     * @param string $newfilename 缩略图的文件名，当为空时，缩略图文件名为 原图_s.后缀
     * @return bool|string
     */
    public static function getThumbnail($source, $savepath, $thumbnailsize, $angle = 0, $newfilename = "")
    {
        //get big picture's info
        $size = getimagesize($source);
        $pathinfo = pathinfo($source);
        $fileext = $pathinfo['extension'];
        $filename = $pathinfo["basename"];
        if ($size[2] == 1) $img = imagecreatefromgif($source);
        if ($size[2] == 2) $img = imagecreatefromjpeg($source);
        if ($size[2] == 3) $img = imagecreatefrompng($source);
        $needResize = 0;
        $angle = intval($angle);
        if ($angle != 0) //如果旋转角度不为0，即要旋转图片
        {
            $img = imagerotate($img, $angle, 16777215); //16777215=背景色=#FFFFFF转为10进制后的数值
            $w1 = round(abs(cos(deg2rad($angle))) * $size[0]);
            $w2 = round(abs(sin(deg2rad($angle))) * $size[1]);
            $h1 = round(abs(cos(deg2rad($angle))) * $size[1]);
            $h2 = round(abs(sin(deg2rad($angle))) * $size[0]);
            $w = $w1 + $w2;
            $h = $h1 + $h2;
            $size[0] = $w;
            $size[1] = $h;
            $needResize = 1;
        }

        if ($img && ($size[0] > $thumbnailsize || $size[1] > $thumbnailsize)) {
            //小图片存为jpeg格式,先设定其文件名
            if ($newfilename == "") {
                $img_newfilename = substr($filename, 0, strrpos($filename, "."));
                $img_newfilename = $img_newfilename . "_s." . $fileext;
            } else {
                $img_newfilename = $newfilename;
            }
            if ($size[0] > $size[1]) {
                //新图片的宽度统一为 $thumbnailsize pixel
                $newsizeW = $thumbnailsize;
                //按比例算出小图片的高度
                $newsizeH = round($size[1] * $newsizeW / $size[0]);
            } else {
                //新图片的高度统一为 $thumbnailsize pixel
                $newsizeH = $thumbnailsize;
                //按比例算出小图片的宽度
                $newsizeW = round($size[0] * $newsizeH / $size[1]);
            }
            $needResize = 1;
        } else {
            $img_newfilename = $newfilename ? $newfilename : $filename;
            $newsizeW = $size[0];
            $newsizeH = $size[1];
        }

        if ($needResize) {
            # find unique color
            do {
                $r = rand(0, 255);
                $g = rand(0, 255);
                $b = rand(0, 255);
            } while (imagecolorexact($img, $r, $g, $b) < 0);

            $img_tmp = ImageCreatetrueColor($newsizeW, $newsizeH); //先建立一个小图容器
            if ($size[2] == 3) { //PNG透明
                //分配颜色 + alpha，将颜色填充到新图上
                $alpha = imagecolorallocatealpha($img_tmp, c, 127);
                imagealphablending($img_tmp, false);//关闭混合模式，以便透明颜色能覆盖原画布
                imagefill($img_tmp, 0, 0, $alpha);
                imagesavealpha($img_tmp, true);//设置保存PNG时保留透明通道信息
            } elseif ($size[2] == 1) { //GIF透明,这会导致GIF图中原来的黑色都变成都变透明了的，这会有问题
                $color = imagecolorallocate($img_tmp, $r, $g, $b);
                imagefill($img_tmp, 0, 0, $color);
                imagecolortransparent($img_tmp, $color);
            }

            imagecopyresampled($img_tmp, $img, 0, 0, 0, 0, $newsizeW, $newsizeH, $size[0], $size[1]);

            if ($size[2] == 1) imagegif($img_tmp, $savepath . "/" . $img_newfilename);
            if ($size[2] == 2) imagejpeg($img_tmp, $savepath . "/" . $img_newfilename, 95);
            if ($size[2] == 3) imagepng($img_tmp, $savepath . "/" . $img_newfilename, 8);
        } else {
            copy($source, $savepath . "/" . $img_newfilename);
        }

        return $img_newfilename;
    }

    /**
     * 转换webp格式并且设定压缩比率
     * @param $source
     * @param $savepath         要保存的文件名
     * @param $newfilename      保存路径
     * @param int $proportion   压缩比率 0 - 100
     */
    public static function getWebp($source, $savepath, &$newfilename, $proportion = 80)
    {
        $size = getimagesize($source);
        $pathinfo = pathinfo($source);
        $filename = $pathinfo["filename"];
        $basename = $pathinfo["basename"];
        $proportion = intval($proportion) <= 0 ? 50 : $proportion;
        if ($size[2] == 1) $img = imagecreatefromgif($source);
        if ($size[2] == 2) $img = imagecreatefromjpeg($source);
        if ($size[2] == 3) $img = imagecreatefrompng($source);

        $newfilename = $filename . '.webp';
        imagewebp($img, $savepath . "/" . $newfilename,$proportion);
        unlink($savepath . "/" . $basename);
    }

    /**
     * 给定缩放最大的参考尺寸，等比例生成缩略图，基于GD库
     * 与getThumbnail的不同在于getThumbnail的参考尺寸是个正方形，参数表示的含义也不一致
     * @param $sourceFilePath 原图文件路径
     * @param $savePath 缩略图保存文件夹路径
     * @param $thumbMaxWidth 最大参考长度
     * @param $thumbMaxHeight 最大参考高度
     * @param string $newFileName 新的文件名
     * @return null|string 成功返回缩略图文件名，否则NULL
     */
    public static function getThumbnailScale($sourceFilePath, $savePath, $thumbMaxWidth, $thumbMaxHeight, $newFileName = '')
    {
        $imgSizeInfo = getimagesize($sourceFilePath);
        $imgWidth = $imgSizeInfo[0];
        $imgHeight = $imgSizeInfo[1];
        $imgType = image_type_to_extension($imgSizeInfo[2], false);
        $imgCreateFun = 'imagecreatefrom'.$imgType;
        if(empty($newFileName)){
            $pathInfo = pathinfo($sourceFilePath);
            $newFileName = $pathInfo["filename"] . "_thumb." . $pathInfo['extension'];
        }
        $saveFilePath = $savePath . DIRECTORY_SEPARATOR . $newFileName;

        //如果原图比缩略图参考尺寸要小，则直接复制
        if($imgWidth <= $thumbMaxWidth && $imgHeight <= $thumbMaxHeight){
            copy($sourceFilePath, $saveFilePath);
            return $newFileName;
        }

        if(!function_exists($imgCreateFun)) return null;

        $img = $imgCreateFun($sourceFilePath);
        $scale = min($thumbMaxWidth/$imgWidth, $thumbMaxHeight/$imgHeight);

        //设置缩略图的宽度和高度
        $thumbWidth  = $imgWidth * $scale;
        $thumbHeight = $imgHeight * $scale;

        # find unique color
        do {
            $r = rand(0, 255);
            $g = rand(0, 255);
            $b = rand(0, 255);
        } while (imagecolorexact($img, $r, $g, $b) < 0);

        $imgThumb = ImageCreatetrueColor($thumbWidth, $thumbHeight); //先建立一个小图容器
        if ($imgType == 'png') {
            //PNG透明，分配颜色 + alpha，将颜色填充到新图上
            $alpha = imagecolorallocatealpha($imgThumb, c, 127);
            imagealphablending($imgThumb, false);//关闭混合模式，以便透明颜色能覆盖原画布
            imagefill($imgThumb, 0, 0, $alpha);
            imagesavealpha($imgThumb, true);//设置保存PNG时保留透明通道信息
        } elseif ($imgType == 'gif') {
            //GIF透明，这会导致GIF图中原来的黑色都变成都变透明了的，这会有问题
            $color = imagecolorallocate($imgThumb, $r, $g, $b);
            imagefill($imgThumb, 0, 0, $color);
            imagecolortransparent($imgThumb, $color);
        }

        imagecopyresampled($imgThumb, $img, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $imgWidth, $imgHeight);
        $saveFun = 'image'.$imgType;

        //销毁原图
        imagedestroy($img);

        //保存缩略图
        if(function_exists($saveFun)){
            $saveFun($imgThumb, $saveFilePath);
            return $newFileName;
        }

        return null;
    }
}
?>