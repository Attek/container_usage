<?php


namespace attek\usage\controllers;

use attek\usage\SendSms;
use Yii;
use yii\console\Controller;


class UsageController extends Controller {


    public $adminEmail;
    public $senderEmail;
    public $maxUsers = 100;
    public $memoryUsageInPercent = 80;
    public $phpContainerRebooted = true;
    public $sqlContainerRebooted = false;
    public $sms_service_url;
    public $sms_auth_user;
    public $sms_auth_pass;
    public $adminPhone;
    public $sqlUptimeLimit;

    public function init() {
        $module = $this->module;
        $this->adminEmail = $module->adminEmail;
        $this->senderEmail = $module->senderEmail;
        $this->maxUsers = $module->maxUsers;
        $this->memoryUsageInPercent = $module->memoryUsageInPercent;
        $this->phpContainerRebooted = $module->phpContainerRebooted;
        $this->sqlContainerRebooted = $module->sqlContainerRebooted;
        $this->sms_service_url = $module->sms_service_url;
        $this->sms_auth_user = $module->sms_auth_user;
        $this->sms_auth_pass = $module->sms_auth_pass;
        $this->adminPhone = $module->adminPhone;
        $this->sqlUptimeLimit = $module->sqlUptimeLimit;
        parent::init();
    }


    /**
     * php yii usage
     */
    public function actionIndex() {

        if ($this->maxUsers > 0) {
            $users = exec( 'ps -aux | grep apache2 | wc -l' );
            //apache szalak szama
            if ( $users > $this->maxUsers ) {
                $message = Yii::$app->name . ' alkalmazásnál a kapcsolatok száma ' . $users . ', indíts új docker konténert!';
                $this->sendAlert( $message );
                Yii::info( $message, 'usage' );
                echo $message . "\n";
            }
        }

        //memoria kihasznaltsag
        if ($this->memoryUsageInPercent > 0) {

            $memory_limit =  exec('cat /sys/fs/cgroup/memory/memory.limit_in_bytes');
            $memory_usage = exec('cat /sys/fs/cgroup/memory/memory.usage_in_bytes');
            $memory_usage_percent = $memory_usage/$memory_limit * 100;

            if ($memory_usage_percent > $this->memoryUsageInPercent) {
                $message =  Yii::$app->name . ' alkalmazás memória felhasználása: ' . number_format($memory_usage_percent, 2) . '% !';
                $this->sendAlert( $message );
                Yii::info($message, 'usage');
                echo $message . "\n";
            }
        }


        if ($this->phpContainerRebooted) {
            $filePath = '/root/.not_rebooted';
            if ( ! is_file( $filePath ) ) {
                $file = fopen( $filePath, "w" );
                fclose( $file );
                $message = Yii::$app->name . ' konténer újraindult!';
                $this->sendAlert( $message );
                Yii::info( $message, 'usage' );
                echo $message . "\n";
            }
        }

        if ($this->sqlContainerRebooted) {
            $connection = Yii::$app->db;
            $sqlUptime = $connection->createCommand('SHOW STATUS WHERE Variable_name = "Uptime"')->queryOne();
            if (isset($sqlUptime['Value']) && $sqlUptime['Value'] < $this->sqlUptimeLimit * 60) {
                $message =  Yii::$app->name . ' MYSQL újraindult! Uptime: ' . $sqlUptime['Value'] . ' mp.';
                $this->sendAlert( $message );
                Yii::info($message, 'usage');
                echo $message . "\n";
            }
        }

    }

    /**
     * @param string $message
     */
    private function sendAlert( string $message ): void {

        if (!empty($this->adminEmail) && !empty($this->senderEmail)) {
            $sent = Yii::$app->mailer->compose()
                                     ->setFrom( $this->senderEmail )
                                     ->setSubject( Yii::$app->name . ' túlterhelés' )
                                     ->setTextBody($message)
                                     ->setTo( $this->adminEmail )
                                     ->send();

            if ( ! $sent ) {
                echo 'Usage email send error';
                Yii::error( 'Usage email send error' );
            }
        }

        if (!empty($this->sms_service_url) && !empty($this->sms_auth_user) && !empty($this->sms_auth_pass) && !empty($this->adminPhone)) {
            $sendSms = new SendSms( $this->sms_service_url, $this->sms_auth_user, $this->sms_auth_pass, $this->adminPhone, $message, 0 );
            if ( ! $sendSms->getResult() ) {
                echo 'SMS send error:' . $sendSms->getMessage();
                Yii::error( 'SMS send error:' . $sendSms->getMessage() );
            }
        }

    }
}
