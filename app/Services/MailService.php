<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Models\User;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class MailService
{
    public function remindTraffic (User $user)
    {
        if (!$user->remind_traffic) return;
        if (!$this->remindTrafficIsWarnValue($user->u, $user->d, $user->transfer_enable, 80)) return;
        $flag = CacheKey::get('LAST_SEND_EMAIL_REMIND_TRAFFIC', $user->id);
        if (Cache::get($flag)) return;
        $permanently_flag = 9999999999;  //长期有效
        $expired_at = $user->expired_at;
        if ($expired_at == NULL) $expired_at = $permanently_flag;    //长期有效
        $nowTime = time();
        if ($nowTime >= $expired_at ) return;
        if ($expired_at == $permanently_flag) {  //判断是否长期有效
            $expired_at = 30 * 24 *3600;  //一个月提醒一次
        }
        else{
            $expired_at = $expired_at - $nowTime;  //过期时间内提醒一次
        }
        
        if (!Cache::put($flag, 1, $expired_at)) return;
        SendEmailJob::dispatch([
            'email' => $user->email,
            'subject' => __('The traffic usage in :app_name has reached 80%', [
                'app_name' => config('v2board.app_name', 'V2board')
            ]),
            'template_name' => 'remindTraffic',
            'template_value' => [
                'name' => config('v2board.app_name', 'V2Board'),
                'url' => config('v2board.app_url')
            ]
        ]);
    }

    public function remindExpire(User $user)
    {
        if (!($user->expired_at !== NULL && ($user->expired_at - 86400) < time() && $user->expired_at > time())) return;
        SendEmailJob::dispatch([
            'email' => $user->email,
            'subject' => __('The service in :app_name is about to expire', [
               'app_name' =>  config('v2board.app_name', 'V2board')
            ]),
            'template_name' => 'remindExpire',
            'template_value' => [
                'name' => config('v2board.app_name', 'V2Board'),
                'url' => config('v2board.app_url')
            ]
        ]);
    }

    //warnValue 流量阈值 80，90，95
    private function remindTrafficIsWarnValue($u, $d, $transfer_enable, $warnValue)
    {
        $ud = $u + $d;
        if (!$ud) return false;
        if (!$transfer_enable) return false;
        $percentage = ($ud / $transfer_enable) * 100;
        if ($percentage < $warnValue) return false;
        if ($percentage >= 100) return false;
        return true;
    }
}
