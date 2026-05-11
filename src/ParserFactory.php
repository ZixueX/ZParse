<?php
namespace MediaParser;

use MediaParser\Parsers\AcfunParser;
use MediaParser\Parsers\BilibiliParser;
use MediaParser\Parsers\DoupaiParser;
use MediaParser\Parsers\DouyinParser;
use MediaParser\Parsers\HaokanParser;
use MediaParser\Parsers\HuyaParser;
use MediaParser\Parsers\InstagramParser;
use MediaParser\Parsers\KuaishouParser;
use MediaParser\Parsers\LishipinParser;
use MediaParser\Parsers\LvzhouParser;
use MediaParser\Parsers\MeipaiParser;
use MediaParser\Parsers\PipigaoxiaoParser;
use MediaParser\Parsers\PipixiaParser;
use MediaParser\Parsers\QuanminParser;
use MediaParser\Parsers\QuanminkgeParser;
use MediaParser\Parsers\SixroomParser;
use MediaParser\Parsers\TiktokParser;
use MediaParser\Parsers\TwitterParser;
use MediaParser\Parsers\WeiboParser;
use MediaParser\Parsers\WeishiParser;
use MediaParser\Parsers\XiaohongshuParser;
use MediaParser\Parsers\XiguaParser;
use MediaParser\Parsers\XinpianchangParser;
use MediaParser\Parsers\YoutubeParser;
use MediaParser\Parsers\ZhihuParser;
use MediaParser\Parsers\ZuiyouParser;

final class ParserFactory
{
    public static function createParser(string $platform, string $realUrl): object
    {
        $map = [
            '小红书' => XiaohongshuParser::class,
            '抖音' => DouyinParser::class,
            '快手' => KuaishouParser::class,
            '哔哩哔哩' => BilibiliParser::class,
            '好看视频' => HaokanParser::class,
            '微视' => WeishiParser::class,
            '梨视频' => LishipinParser::class,
            '皮皮搞笑' => PipigaoxiaoParser::class,
            'AcFun' => AcfunParser::class,
            'Instagram' => InstagramParser::class,
            'TikTok' => TiktokParser::class,
            'Twitter' => TwitterParser::class,
            '微博' => WeiboParser::class,
            '西瓜视频' => XiguaParser::class,
            'YouTube' => YoutubeParser::class,
            '知乎' => ZhihuParser::class,
            '逗拍' => DoupaiParser::class,
            '虎牙' => HuyaParser::class,
            '绿洲' => LvzhouParser::class,
            '美拍' => MeipaiParser::class,
            '皮皮虾' => PipixiaParser::class,
            '全民小视频' => QuanminParser::class,
            '全民K歌' => QuanminkgeParser::class,
            '六间房' => SixroomParser::class,
            '新片场' => XinpianchangParser::class,
            '最右' => ZuiyouParser::class,
        ];
        if (!isset($map[$platform])) {
            throw new \InvalidArgumentException('Unsupported platform: ' . $platform);
        }
        $class = $map[$platform];
        return new $class($realUrl);
    }
}
