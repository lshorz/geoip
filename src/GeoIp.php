<?php
declare(strict_types=1);

namespace Lshorz\GeoIp;

/**
 *  基于纯真IP库的地理位置
 */
class GeoIp
{
    /**
     * @var resource
     */
    private $fp;

    /**
     * 第一条IP记录的偏移地址
     */
    private int $firstIp;

    /**
     * 最后一条IP记录的偏移地址
     */
    private int $lastIp;

    /**
     * IP记录的总条数（不包含版本信息记录）
     */
    private int $totalIp;

    public function __construct()
    {
        $this->fp = 0;
        $filename = dirname(__FILE__) . '/data/qqwry.dat';
        if (($this->fp = fopen($filename, 'rb')) !== false) {
            $this->firstIp = $this->getLong();
            $this->lastIp = $this->getLong();
            $this->totalIp = ($this->lastIp - $this->firstIp) / 7;
        }
    }

    /**
     * 返回读取的长整型数
     *
     * @access private
     * @return int
     */
    private function getLong(): int
    {
        //将读取的little-endian编码的4个字节转化为长整型数
        $result = unpack('Vlong', fread($this->fp, 4));
        return $result['long'];
    }

    /**
     * 返回读取的3个字节的长整型数
     *
     * @access private
     * @return int
     */
    private function getLong3(): int
    {
        //将读取的little-endian编码的3个字节转化为长整型数
        $result = unpack('Vlong', fread($this->fp, 3) . chr(0));
        return $result['long'];
    }

    /**
     * 返回压缩后可进行比较的IP地址
     *
     * @access private
     * @param string $ip
     * @return string
     */
    private function packIp(string $ip): string
    {
        // 将IP地址转化为长整型数，如果在PHP5中，IP地址错误，则返回False，
        // 这时intval将Flase转化为整数-1，之后压缩成big-endian编码的字符串
        return pack('N', intval(ip2long($ip)));
    }

    /**
     * 返回读取的字符串
     *
     * @access private
     * @param string $data
     * @return string
     */
    private function getString(string $data = ""): string
    {
        $char = fread($this->fp, 1);
        while (ord($char) > 0) {
            // 字符串按照C格式保存，以\0结束
            $data .= $char; // 将读取的字符连接到给定字符串之后
            $char = fread($this->fp, 1);
        }
        //对结果进行转码
        if (extension_loaded('mbstring')) {
            return mb_convert_encoding($data, 'UTF-8', 'GBK');  //GBK to $charset
        } else {
            return iconv('GBK', 'UTF-8', $data);   //GBK to $charset
        }
    }

    /**
     * 返回地区信息
     *
     * @access private
     * @return string
     */
    private function getArea(): string
    {
        $byte = fread($this->fp, 1); // 标志字节
        switch (ord($byte)) {
            case 0: // 没有区域信息
                $area = "";
                break;
            case 1:
            case 2: // 标志字节为1或2，表示区域信息被重定向
                fseek($this->fp, $this->getLong3());
                $area = $this->getString();
                break;
            default: // 否则，表示区域信息没有被重定向
                $area = $this->getString($byte);
                break;
        }
        return $area;
    }

    /**
     * 根据所给 IP 地址或域名返回所在地区信息
     *
     * @access public
     * @param string $ip
     * @return array|null
     */
    public function getLocation(?string $ip = null): ?array
    {
        if (!$this->fp) {
            return null;
        }
        // 如果数据文件没有被正确打开，则直接返回空
        if (empty($ip)) {
            $ip = $this->getClientIp();
        }

        $location['ip'] = gethostbyname($ip); // 将输入的域名转化为IP地址
        $ip = $this->packIp($location['ip']); // 将输入的IP地址转化为可比较的IP地址
        // 不合法的IP地址会被转化为255.255.255.255
        // 对分搜索
        $l = 0; // 搜索的下边界
        $u = $this->totalIp; // 搜索的上边界
        $findIp = $this->lastIp; // 如果没有找到就返回最后一条IP记录（QQWry.Dat的版本信息）
        while ($l <= $u) {
            // 当上边界小于下边界时，查找失败
            $i = floor(($l + $u) / 2); // 计算近似中间记录
            fseek($this->fp, (int)($this->firstIp + $i * 7));
            $beginIp = strrev(fread($this->fp, 4)); // 获取中间记录的开始IP地址
            // strrev函数在这里的作用是将little-endian的压缩IP地址转化为big-endian的格式
            // 以便用于比较，后面相同。
            if ($ip < $beginIp) { // 用户的IP小于中间记录的开始IP地址时
                $u = $i - 1; // 将搜索的上边界修改为中间记录减一
            } else {
                fseek($this->fp, $this->getLong3());
                $endIp = strrev(fread($this->fp, 4)); // 获取中间记录的结束IP地址
                if ($ip > $endIp) { // 用户的IP大于中间记录的结束IP地址时
                    $l = $i + 1; // 将搜索的下边界修改为中间记录加一
                } else {
                    // 用户的IP在中间记录的IP范围内时
                    $findIp = $this->firstIp + $i * 7;
                    break; // 则表示找到结果，退出循环
                }
            }
        }

        //获取查找到的IP地理位置信息
        fseek($this->fp, (int)$findIp);
        $location['beginIp'] = long2ip($this->getLong()); // 用户IP所在范围的开始地址
        $offset = $this->getLong3();
        fseek($this->fp, $offset);
        $location['endIp'] = long2ip($this->getLong()); // 用户IP所在范围的结束地址
        $byte = fread($this->fp, 1); // 标志字节
        switch (ord($byte)) {
            case 1: // 标志字节为1，表示国家和区域信息都被同时重定向
                $countryOffset = $this->getLong3(); // 重定向地址
                fseek($this->fp, $countryOffset);
                $byte = fread($this->fp, 1); // 标志字节
                switch (ord($byte)) {
                    case 2: // 标志字节为2，表示国家信息又被重定向
                        fseek($this->fp, $this->getLong3());
                        $location['country'] = $this->getString();
                        fseek($this->fp, $countryOffset + 4);
                        $location['area'] = $this->getArea();
                        break;
                    default: // 否则，表示国家信息没有被重定向
                        $location['country'] = $this->getString($byte);
                        $location['area'] = $this->getArea();
                        break;
                }
                break;
            case 2: // 标志字节为2，表示国家信息被重定向
                fseek($this->fp, $this->getLong3());
                $location['country'] = $this->getString();
                fseek($this->fp, $offset + 8);
                $location['area'] = $this->getArea();
                break;
            default: // 否则，表示国家信息没有被重定向
                $location['country'] = $this->getString($byte);
                $location['area'] = $this->getArea();
                break;
        }
        if (trim($location['country']) == 'CZ88.NET') {
            // CZ88.NET表示没有有效信息
            $location['country'] = '未知';
        }
        if (trim($location['area']) == 'CZ88.NET') {
            $location['area'] = '';
        }
        $location['location'] = $location['country'] . $location['area'];
        return $location;
    }

    /**
     * 获取Ip地址
     *
     * @return string
     */
    private function getClientIp(): string
    {
        $remotes_keys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
            'HTTP_X_CLUSTER_CLIENT_IP',
        ];

        foreach ($remotes_keys as $key) {
            if ($address = getenv($key)) {
                foreach (explode(',', $address) as $ip) {
                    if ($this->isValid($ip)) {
                        return $ip;
                    }
                }
            }
        }
        return '127.0.0.0';
    }

    /**
     * Checks if the ip is valid.
     *
     * @param string $ip
     *
     * @return bool
     */
    private function isValid(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE)
        ) {
            return false;
        }

        return true;
    }

    /**
     * 析构函数，用于在页面执行结束后自动关闭打开的文件。
     *
     */
    public function __destruct()
    {
        if ($this->fp) {
            fclose($this->fp);
        }
        $this->fp = 0;
    }

}
