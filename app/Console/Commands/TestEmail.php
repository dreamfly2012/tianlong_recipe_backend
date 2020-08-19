<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Mail;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:mail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '发送邮件';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $name = '王宝花';
        $imgPath = 'http://b.hiphotos.baidu.com/image/pic/item/08f790529822720e45023d1277cb0a46f31fab0e.jpg';
        // Mail::send()的返回值为空，所以可以其他方法进行判断
        Mail::send('emails.test',['name'=>$name,'imgPath'=>$imgPath],function($message){
            $from = env('MAIL_USERNAME');
            $to = '574674880@qq.com';
            $cc = env('MAIL_USERNAME');
            $message ->from($from)->to($to)->cc($cc)->subject('邮件测试');
        });
        // 返回的一个错误数组，利用此可以判断是否发送成功
        dd(Mail::failures());

    }
}
