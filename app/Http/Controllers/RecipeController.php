<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\RecipeIngredient;
use App\RecipeDirection;
use App\Recipe;
use App\User;
use App\RecipeCategory;
use File;

class RecipeController extends Controller
{
    public function __construct()
    {
    	$this->middleware('auth:api')
    		->except(['index', 'show', 'lists', 'info', 'category']);
    }

    public function lists(Request $request)
    {
        $category_id = $request->get('category_id',  0);

        if(empty($category_id)){
            $cacheRecipes = Redis::get('recipes');
            if ($cacheRecipes) {
                return $cacheRecipes;
            } else {
                $recipes = Recipe::select()->orderBy('id', 'desc')->paginate();
                foreach ($recipes as $k => $v) {
                    $cdn = getenv('COSV5_CDN');

                    $recipes[$k]['image_src'] = $cdn . $v['image'];
                }
                Redis::setex('recipes', 600, $recipes);
                return $recipes;
            }
        }else{
            $cache_key = 'recipes:' . $category_id;
            $cacheRecipes = Redis::get($cache_key);
            if ($cacheRecipes) {
                return $cacheRecipes;
            } else {
                $recipes = Recipe::where('category_id', $category_id)->orderBy('id', 'desc')->paginate();
                foreach ($recipes as $k => $v) {
                    $cdn = getenv('COSV5_CDN');

                    $recipes[$k]['image_src'] = $cdn . $v['image'];
                }
                Redis::setex($cache_key, 600, $recipes);
                return $recipes;
            }
        }
        
    }



    public function category(Request $request)
    {
        $parent_id = $request->get('parent_id', 0);

        $cacheCategories = Redis::get('categories');
        if($cacheCategories){
            return ['msg'=>'分类信息', 'data'=>$cacheCategories,'code'=>0];
        }else{
            $categories = RecipeCategory::where('parent_id', $parent_id)->orderBy('category_id','asc')->get();

            Redis::setex('cacheCategories', 600, $categories);

            return ['msg' => '分类信息','code'=>0, 'data' => $categories];
        }
    }



    public function info(Request $request)
    {
        $id = $request->get('id');
        
        $cacheRecipes = Redis::get('recipe:'.$id);
        if($cacheRecipes) {
           return $cacheRecipes;
        } else {
        
            $recipe = \App\Recipe::where('id',$id)->with(['directions','ingredients'])->first();
            if(empty($recipe)){
                return [];
            }
            $cdn = getenv('COSV5_CDN');

            $recipe['image_src'] = $cdn .$recipe['image'];

            Redis::setex('recipe:'.$id, 600, $recipe);
            return $recipe;
        }
    }

    public function index()
    {
    	$recipes = Recipe::orderBy('created_at', 'desc')
    		->get(['id', 'name', 'image']);

    	return response()
    		->json([
    			'recipes' => $recipes
    		]);
    }

    public function create()
    {
        $form = Recipe::form();
        $categories = RecipeCategory::where('parent_id', 0)->get();
        //获取
    	return response()
    		->json([
                'form' => $form,
                'cateories' => $categories,
    		]);
    }

    public function store(Request $request)
    {
    	$this->validate($request, [
    		'name' => 'required|max:255',
    		'description' => 'required|max:3000',
    		'image' => 'required|image',
    		'ingredients' => 'required|array|min:1',
    		'ingredients.*.name' => 'required|max:255',
    		'ingredients.*.qty' => 'required|max:255',
    		'directions' => 'required|array|min:1',
    		'directions.*.description' => 'required|max:3000'
    	]);

    	$ingredients = [];

        foreach($request->ingredients as $ingredient) {
            $ingredients[] = new RecipeIngredient($ingredient);
        }

	   $directions = [];

        foreach($request->directions as $direction) {
            $directions[] = new RecipeDirection($direction);
        }

    	if(!$request->hasFile('image') && !$request->file('image')->isValid()) {
    		return abort(404, 'Image not uploaded!');
    	}

    	$filename = $this->getFileName($request->image);
    	
    	$FilePath = $request->image->getRealPath(); //获取文件临时存放位置
    
    	Storage::disk('cosv5')->put($filename, file_get_contents($FilePath)); //存储文件
    	
    	$request->image->move(base_path('public/images'), $filename);

    	$recipe = new Recipe($request->only('name', 'description','category_id'));
        $recipe->image = $filename;
        
        $request->user()->recipes()
    		->save($recipe);

    	$recipe->ingredients()
    		->saveMany($ingredients);

    	$recipe->directions()
    		->saveMany($directions);

    	return response()
    	    ->json([
    	        'saved' => true,
    	        'id' => $recipe->id,
                'message' => 'You have successfully created recipe!'
    	    ]);
    }

    private function getFileName($file)
    {
    	return str_random(32).'.'.$file->extension();
    }

    public function show($id)
    {
        $recipe = Recipe::with(['user', 'ingredients', 'directions'])
            ->findOrFail($id);

        return response()
            ->json([
                'recipe' => $recipe
            ]);
    }


    public function edit($id, Request $request)
    {
        $form = $request->user()->recipes()
            ->with(['ingredients' => function($query) {
                $query->get(['id', 'name', 'qty']);
            }, 'directions' => function($query) {
                $query->get(['id', 'description']);
            }])
            ->findOrFail($id, [
                'id', 'name', 'description', 'image'
            ]);

        return response()
            ->json([
                'form' => $form
            ]);
    }

    public function update($id, Request $request)
    {
        // dd($request->all());
        $this->validate($request, [
            'name' => 'required|max:255',
            'description' => 'required|max:3000',
            'image' => 'image',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.id' => 'integer|exists:recipe_ingredients',
            'ingredients.*.name' => 'required|max:255',
            'ingredients.*.qty' => 'required|max:255',
            'directions' => 'required|array|min:1',
            'directions.*.id' => 'integer|exists:recipe_directions',
            'directions.*.description' => 'required|max:3000'
        ]);

        $recipe = $request->user()->recipes()
            ->findOrFail($id);

        $ingredients = [];
        $ingredientsUpdated = [];

        foreach($request->ingredients as $ingredient) {
            if(isset($ingredient['id'])) {
                RecipeIngredient::where('recipe_id', $recipe->id)
                    ->where('id', $ingredient['id'])
                    ->update($ingredient);

                $ingredientsUpdated[] = $ingredient['id'];
            } else {
                $ingredients[] = new RecipeIngredient($ingredient);
            }
        }

        $directions = [];
        $directionsUpdated = [];

        foreach($request->directions as $direction) {
            if(isset($direction['id'])) {
                RecipeDirection::where('recipe_id', $recipe->id)
                    ->where('id', $direction['id'])
                    ->update($direction);

                $directionsUpdated[] = $direction['id'];
            } else {
                $directions[] = new RecipeDirection($direction);
            }

        }

        $recipe->name = $request->name;
        $recipe->description = $request->description;

        // upload image
        if ($request->hasfile('image') && $request->file('image')->isValid()) {
            $filename = $this->getFileName($request->image);
            $request->image->move(base_path('/public/images'), $filename);

            // remove old image
            File::delete(base_path('/public/images/'.$recipe->image));
            $recipe->image = $filename;
        }

        $recipe->save();

        RecipeIngredient::whereNotIn('id', $ingredientsUpdated)
            ->where('recipe_id', $recipe->id)
            ->delete();

        RecipeDirection::whereNotIn('id', $directionsUpdated)
            ->where('recipe_id', $recipe->id)
            ->delete();

        if(count($ingredients)) {
            $recipe->ingredients()->saveMany($ingredients);
        }

        if(count($directions)) {
            $recipe->directions()->saveMany($directions);
        }

        return response()
            ->json([
                'saved' => true,
                'id' => $recipe->id,
                'message' => 'You have successfully updated recipe!'
            ]);
    }

    public function destroy($id, Request $request)
    {
        $recipe = $request->user()->recipes()
            ->findOrFail($id);

        RecipeIngredient::where('recipe_id', $recipe->id)
            ->delete();

        RecipeDirection::where('recipe_id', $recipe->id)
            ->delete();

        // remove image
        File::delete(base_path('/public/images/'.$recipe->image));

        $recipe->delete();

        return response()
            ->json([
                'deleted' => true
            ]);
    }
    
    
    public function upload(Request $request)
    {
        $file = $request->file('file');

        if ($file->isValid()) { //括号里面的是必须加的哦
            //如果括号里面的不加上的话，下面的方法也无法调用的

            //获取文件的扩展名
            $ext = $file->getClientOriginalExtension();

            //获取文件的绝对路径
            $path = $file->getRealPath();

            //$path = 'http://ljfl.menghuiguli.cn/storage/2019-08-05-05-46-22.mp3';

            $response = $this->sendvoice($path, 'mp3');

            die(json_encode(array('code' => 0, 'msg' => 'success', 'data' => $response)));

            return $response;

            //定义文件名
            //$filename = date('Y-m-d-h-i-s').'.'.$ext;

            //存储文件。disk里面的public。总的来说，就是调用disk模块里的public配置
            //Storage::disk('public')->put($filename, file_get_contents($path));
        } else {
            dump('error');
        }

    }

    public function sendvoice($filepath, $VoiceFormat)
    {
        $secret_key = config('app.secretkey');
        $query_arr = array();
        $query_arr['Action'] = 'SentenceRecognition';
        $query_arr['SecretId'] = config('app.secretid');
        $query_arr['Timestamp'] = time();
        $query_arr['Nonce'] = substr($query_arr['Timestamp'], 0, 4);
        $query_arr['Version'] = '2018-05-22';
        $query_arr['ProjectId'] = 0;
        $query_arr['SubServiceType'] = 2;
        $query_arr['EngSerViceType'] = '8k';
        $query_arr['SourceType'] = 1;
        if ($query_arr['SourceType'] == 0) {
            $voice = $filepath;
            $voice = urlencode($voice);
            $query_arr['Url'] = $voice;
        } else if ($query_arr['SourceType'] == 1) {
            $file_path = $filepath;
            if (file_exists($file_path)) {
                //echo $file_path;
                $handle = fopen($file_path, "rb");
                $str = fread($handle, filesize($file_path));
                fclose($handle);
                $strlen = strlen($str);
                $str = base64_encode($str);
                $query_arr["Data"] = $str;
                $query_arr["DataLen"] = $strlen;
            } else {
                return -3;
            }
        }
        $query_arr['VoiceFormat'] = $VoiceFormat;
        $query_arr['UsrAudioKey'] = $this->randstr(16);

        ksort($query_arr);

        $signStr = $this->formatSignString('aai.tencentcloudapi.com', '/', $query_arr, 'POST');

        $sign = base64_encode(hash_hmac('sha1', $signStr, $secret_key, true));

        $query_arr['Signature'] = $sign;

        $url = 'https://aai.tencentcloudapi.com';
        $headers = array("Host:aai.tencentcloudapi.com", "Content-Type:application/x-www-form-urlencoded", "charset=UTF-8");
        $http_code = -1;
        $rsp_str = "";
        $starttime = time();
        $ret = $this->http_curl_exec($url, $query_arr, $rsp_str, $http_code, 'POST', 10, array(), $headers);
        //echo "ret : ".$ret."\n";
        // echo "http_code : ".$http_code."\n";
        // echo "rsp_str : ".$rsp_str."\n";

        $info = json_decode($rsp_str, true);

        $endtime = time();
        $cost = $endtime - $starttime;

        if (isset($info['Response']['Error'])) {
            return $info['Response']['Error']['Message'];
        } else {
            return $info['Response']['Result'];
        }
        //echo "cost time: ".$cost."\n";

    }

    public function formatSignString($host, $uri, $param, $requestMethod)
    {
        $tmpParam = array();
        ksort($param);
        foreach ($param as $key => $value) {
            array_push($tmpParam, str_replace("_", ".", $key) . "=" . $value);
        }
        $strParam = join("&", $tmpParam);
        $signStr = strtoupper($requestMethod) . $host . $uri . "?" . $strParam;
        return $signStr;
    }

    public function randstr($num)
    {
        $str = "QWERTYUIOPASDFGHJKLZXCVBNM1234567890qwertyuiopasdfghjklzxcvbnm";
        str_shuffle($str);
        $name = substr(str_shuffle($str), 26, $num);
        return $name;
    }

    public function createSign($reqArr, $method, $domain, $path, $secretKey)
    {
        $signStr = "";
        $signStr .= $method;
        $signStr .= $domain;
        $signStr .= $path;
        $signStr .= $reqArr['appid'];
        $signStr .= "?";

        ksort($reqArr, SORT_STRING);

        foreach ($reqArr as $key => $val) {
            if ($key == "appid") {
                continue;
            }

            $signStr .= $key . "=" . $val . "&";
        }
        $signStr = substr($signStr, 0, -1);
        //echo "plainText : \n".$signStr."\n";

        $signStr = base64_encode(hash_hmac('SHA1', $signStr, $secretKey, true));

        return $signStr;
    }

    /**
     * 判断参数，发出Http请求，成功则返回0，失败时返回负数。识别结果会赋值给$rsp_str变量。
     */
    public function http_curl_exec($url, $data, &$rsp_str, &$http_code, $method = 'POST', $timeout = 10, $cookie = array(), $headers = array())
    {
        $ch = curl_init();
        if (!curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1)) {
            return -1;
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if (count($headers) > 0) {
            if (!curl_setopt($ch, CURLOPT_HTTPHEADER, $headers)) {
                return -1;
            }
        }

        if ($method != 'GET') {
            $data = is_array($data) ? http_build_query($data) : $data;
            if (!curl_setopt($ch, CURLOPT_POSTFIELDS, $data)) {
                echo 'http_curl_ex set postfields failed';
                return -1;
            }
        } else {
            $data = is_array($data) ? http_build_query($data) : $data;
            echo 'data (GET method) : ' . $data . "\n";
            if (strpos($url, '?') === false) {
                $url .= '?';
            } else {
                $url .= '&';
            }
            $url .= $data;
        }

        if (!curl_setopt($ch, CURLOPT_URL, $url)) {
            return -1;
        }

        if (!curl_setopt($ch, CURLOPT_TIMEOUT, $timeout)) {
            return -1;
        }

        if (!curl_setopt($ch, CURLOPT_HEADER, 0)) {
            return -1;
        }

        $rsp_str = curl_exec($ch);
        if ($rsp_str === false) {
            var_dump(curl_error($ch));
            curl_close($ch);
            return -2;
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return 0;
    }
}
