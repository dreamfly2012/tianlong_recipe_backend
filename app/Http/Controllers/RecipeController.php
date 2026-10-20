<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\RecipeIngredient;
use App\Models\RecipeDirection;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Http\Helpers\ApiResponse;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="天龙食谱 API 文档",
 *      description="天龙食谱后端 API 接口文档"
 * )
 * 
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description="API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *      securityScheme="bearerAuth",
 *      type="http",
 *      scheme="bearer",
 *      bearerFormat="JWT"
 * )
 */

class RecipeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')
            ->except(['index', 'show', 'lists', 'info', 'category', 'search']);
    }

    /**
     * @OA\Get(
     *      path="/api/recipe.lists",
     *      operationId="getRecipesList",
     *      tags={"Recipes"},
     *      summary="获取食谱列表",
     *      description="返回所有食谱或根据分类ID获取食谱列表",
     *      @OA\Parameter(
     *          name="id",
     *          description="分类ID",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="成功操作"
     *       ),
     *       @OA\Response(response=400, description="无效请求")
     * )
     */
    public function lists(Request $request)
    {
        $category_id = $request->get('id',  0);

        if(empty($category_id)){
            $cacheRecipes = Redis::get('recipes');
           
            if ($cacheRecipes) {
                $cacheRecipes = unserialize($cacheRecipes);
                return $cacheRecipes;
            } else {
                $recipes = Recipe::orderBy('id', 'desc')->paginate();
                
                foreach ($recipes as $k => $v) {
                    $cdn = getenv('COS_CDN');
                    $recipes[$k]['image_src'] = $cdn . $v['image'];
                }
                $s_recipes = serialize($recipes);
                Redis::setex('recipes', 600, $s_recipes);
                $cacheRecipes = Redis::get('recipes');
                return $recipes;
            }
        }else{
            $cache_key = 'recipes:' . $category_id;
            $cacheRecipes = Redis::get($cache_key);
            if ($cacheRecipes) {
                $cacheRecipes = unserialize($cacheRecipes);
                return $cacheRecipes;
            } else {
                $recipes = Recipe::where('category_id', $category_id)->orderBy('id', 'desc')->paginate();
                foreach ($recipes as $k => $v) {
                    $cdn = getenv('COS_CDN');
                    $recipes[$k]['image_src'] = $cdn . $v['image'];
                }
                $s_recipes = serialize($recipes);
                Redis::setex($cache_key, 600, $s_recipes);
                return $recipes;
            }
        }
        
    }


    //每日推荐
    /**
     * @OA\Get(
     *      path="/api/recipe.daily",
     *      operationId="getDailyRecipes",
     *      tags={"Recipes"},
     *      summary="获取每日推荐食谱",
     *      description="返回每日推荐的食谱列表",
     *      @OA\Response(
     *          response=200,
     *          description="成功操作"
     *       )
     * )
     */
    public function daily(Request $request)
    {
        $recipes = Recipe::orderBy('id', 'desc')->paginate(15);
        foreach ($recipes as $k => $v) {
            $cdn = getenv('COS_CDN');
            $recipes[$k]['image_src'] = $cdn . $v['image'];
        }
        return $recipes;
    }

    //根据ai生成食谱
    /**
     * @OA\Get(
     *      path="/api/recipe.ai",
     *      operationId="getAiRecipes",
     *      tags={"Recipes"},
     *      summary="获取AI生成的食谱",
     *      description="返回由AI生成的食谱列表",
     *      @OA\Response(
     *          response=200,
     *          description="成功操作"
     *       )
     * )
     */
    public function ai(Request $request)
    {
        $recipes = Recipe::orderBy('id', 'desc')->paginate(15);
        foreach ($recipes as $k => $v) {
            $cdn = getenv('COS_CDN');
            $recipes[$k]['image_src'] = $cdn . $v['image'];
        }
        return $recipes;
    }

    /**
     * @OA\Get(
     *      path="/api/recipe.personalized",
     *      operationId="getPersonalizedRecipes",
     *      tags={"Recipes"},
     *      summary="获取个性化推荐食谱",
     *      description="返回根据用户偏好推荐的食谱列表",
     *      @OA\Response(
     *          response=200,
     *          description="成功操作"
     *       )
     * )
     */
    public function personalized(Request $request)
    {
        $recipes = Recipe::orderBy('id', 'desc')->paginate(2);
        foreach ($recipes as $k => $v) {
            $cdn = getenv('COS_CDN');
            $recipes[$k]['image_src'] = $cdn . $v['image'];
        }
        return $recipes;
    }


    /**
     * @OA\Get(
     *      path="/api/recipe.generate",
     *      operationId="getGeneratedRecipes",
     *      tags={"Recipes"},
     *      summary="获取AI生成的食谱",
     *      description="返回由AI生成的食谱列表",
     *      @OA\Response(
     *          response=200,
     *          description="成功操作"
     *       )
     * )
     */
    public function generate(Request $request)
    {
        // 获取请求中的数据
        $ingredientsInput = $request->get('ingredientsInput', '');
        $preferencesInput = $request->get('preferencesInput', '');
        $excludesInput = $request->get('excludesInput', '');
        
        // 解析数据
        $ingredients = explode(',', $ingredientsInput);
        $ingredients = array_map('trim', $ingredients);
        $ingredients = array_filter($ingredients);
        
        $preferences = explode(',', $preferencesInput);
        $preferences = array_map('trim', $preferences);
        $preferences = array_filter($preferences);
        
        $excludes = explode(',', $excludesInput);
        $excludes = array_map('trim', $excludes);
        $excludes = array_filter($excludes);
        
        // 如果有输入食材，根据食材查找菜谱，否则随机获取一条菜谱
        if (!empty($ingredients)) {
            // 这里应该实现AI生成菜谱的逻辑
            // 目前暂时随机获取一条菜谱并携带制作步骤信息
            $recipe = Recipe::with(['directions', 'ingredients'])->inRandomOrder()->first();
        } else {
            // 随机获取一条菜谱并携带制作步骤信息
            $recipe = Recipe::with(['directions', 'ingredients'])->inRandomOrder()->first();
        }
        
        // 如果没有找到菜谱，返回错误信息
        if (!$recipe) {
            return ApiResponse::notFound('未找到菜谱');
        }
        
        // 准备数据
        $cdn = getenv('COS_CDN');
        $recipeData = $recipe->toArray();
        $recipeData['image_src'] = $cdn . $recipe->image;
        
        return ApiResponse::success($recipeData, '获取菜谱成功');
    }


    /**
     * @OA\Get(
     *      path="/api/recipe.search",
     *      operationId="searchRecipes",
     *      tags={"Recipes"},
     *      summary="搜索食谱",
     *      description="根据名称搜索食谱",
     *      @OA\Parameter(
     *          name="name",
     *          description="食谱名称",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="成功操作"
     *       ),
     *       @OA\Response(response=400, description="无效请求")
     * )
     */
    public function search(Request $request)
    {
        $name = $request->get('name');
        
        $recipes = Recipe::where('name','like', '%' . $name . '%')->orderby('id', 'desc')->paginate(15);
        foreach ($recipes as $k => $v) {
            $cdn = getenv('COS_CDN');
            $recipes[$k]['image_src'] = $cdn . $v['image'];
        }
        return $recipes;
    }



    public function category(Request $request)
    {
        $parent_id = $request->get('parent_id', 0);

        $cacheCategories = Redis::get('categories');
        if($cacheCategories){
            return ['msg'=>'分类信息', 'data'=>$cacheCategories,'code'=>0];
        }else{

            $categories = RecipeCategory::where('parent_id', $parent_id)->orderBy('id','asc')->get();
            
            Redis::setex('cacheCategories', 600, $categories);

            return ['msg' => '分类信息','code'=>0, 'data' => $categories];
        }
    }



    /**
     * 获取食谱详情
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function info(Request $request)
    {
        $id = $request->get('id');
        $cacheKey = 'recipe:'.$id;
        
        // 尝试从缓存获取
        $cachedRecipe = Redis::get($cacheKey);
        
        if ($cachedRecipe) {
            return ApiResponse::success(
                json_decode($cachedRecipe, true),
                '获取食谱详情成功'
            );
        }
        
        // 从数据库获取
        $recipe = Recipe::where('id', $id)
            ->with(['directions', 'ingredients'])
            ->first();
            
        if (empty($recipe)) {
            return ApiResponse::notFound('食谱不存在');
        }
        
        // 准备数据
        $cdn = getenv('COS_CDN');
        $recipeData = $recipe->toArray();
        $recipeData['image_src'] = $cdn . $recipe->image;
        
        // 缓存数据（JSON 格式）
        Redis::setex($cacheKey, 600, json_encode($recipeData));
        
        return ApiResponse::success($recipeData, '获取食谱详情成功');
    }

    /**
     * 获取食谱列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $recipes = Recipe::orderBy('created_at', 'desc')
            ->get(['id', 'name', 'image']);

        return ApiResponse::success($recipes, '获取食谱列表成功');
    }

    /**
     * 获取创建表单数据
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        try {
            $form = Recipe::form();
            $categories = RecipeCategory::where('id', 0)->get();
            
            return ApiResponse::success([
                'form' => $form,
                'categories' => $categories,
            ]);
        } catch (\Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * 创建新食谱
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
       
        $validated = $request->validate([
            'name' => 'required|max:255',
            'description' => 'required|max:3000',
            'image' => 'required|image',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.name' => 'required|max:255',
            'ingredients.*.qty' => 'required|max:255',
            'directions' => 'required|array|min:1',
            'directions.*.description' => 'required|max:3000',
            'category_id' => 'required|exists:recipe_categories,id'
        ]);

        $ingredients = [];
        foreach ($request->ingredients as $ingredient) {
            $ingredients[] = new RecipeIngredient($ingredient);
        }

        $directions = [];
        foreach($request->directions as $direction) {
            $directions[] = new RecipeDirection($direction);
        }

        if(!$request->hasFile('image') || !$request->file('image')->isValid()) {
            return abort(404, 'Image not uploaded!');
        }

        $filename = $this->getFileName($request->image);
        $FilePath = $request->image->getRealPath(); // 获取文件临时存放位置
    
        Storage::disk('cos')->put($filename, file_get_contents($FilePath)); // 存储文件
        $request->image->move(base_path('public/images'), $filename);

        $recipe = new Recipe($request->only('name', 'description', 'category_id'));
        $recipe->image = $filename;
        
        $request->user()->recipes()->save($recipe);

        $recipe->ingredients()->saveMany($ingredients);
        $recipe->directions()->saveMany($directions);

        return response()->json([
            'saved' => true,
            'id' => $recipe->id,
            'message' => 'You have successfully created recipe!'
        ]);
    }

    private function getFileName($file)
    {
    	return Str::random(32).'.'.$file->extension();
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
        $categories = RecipeCategory::where('id', 0)->get();
        $form = $request->user()->recipes()
            ->with(['ingredients' => function($query) {
                $query->get(['id', 'name', 'qty']);
            }, 'directions' => function($query) {
                $query->get(['id', 'description']);
            }])
            ->findOrFail($id, [
                'id', 'name', 'description', 'image'
            ]);

        return ApiResponse::success([
            'form' => $form,
            'categories' => $categories,
        ]);
    }

    public function update($id)
    {
        $request = request();
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
        $recipe->category_id = $request->category_id;
        

        // upload image
        if ($request->hasfile('image') && $request->file('image')->isValid()) {
            $filename = $this->getFileName($request->image);
            $request->image->move(base_path('/public/images'), $filename);

            // remove old image
            $FilePath = $request->image->getRealPath(); //获取文件临时存放位置
            Storage::disk('cos')->put($filename, file_get_contents($FilePath)); //存储文件
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

        return ApiResponse::success(
            ['id' => $recipe->id],
                '食谱更新成功'
            );
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
    
    /**
     * 收藏食谱
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function favorite(Request $request)
    {
        $recipeId = $request->get('recipe_id');
        $user = $request->user();
        
        $recipe = Recipe::findOrFail($recipeId);
        
        if (!$user->favoriteRecipes->contains($recipeId)) {
            $user->favoriteRecipes()->attach($recipeId);
            return ApiResponse::success(null, '食谱已收藏');
        }
        
        return ApiResponse::success(null, '食谱已收藏');
    }
    
    /**
     * 取消收藏食谱
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unfavorite(Request $request)
    {
        $recipeId = $request->get('recipe_id');
        $user = $request->user();
        
        $recipe = Recipe::findOrFail($recipeId);
        
        if ($user->favoriteRecipes->contains($recipeId)) {
            $user->favoriteRecipes()->detach($recipeId);
            return ApiResponse::success(null, '已取消收藏');
        }
        
        return ApiResponse::success(null, '已取消收藏');
    }

    /**
     * 判断用户是否收藏了该食谱
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function isFavorite(Request $request)
    {
        $recipeId = $request->get('recipe_id');
        $user = $request->user();
        
        $recipe = Recipe::findOrFail($recipeId);
        
        if ($user->favoriteRecipes->contains($recipeId)) {
            return ApiResponse::success(['isFavorite' => true], '已收藏');
        }
        
        return ApiResponse::success(['isFavorite' => false], '未收藏');
    }
    /**
     * 获取用户收藏的食谱列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function favoriteList(Request $request)
    {
        $user = $request->user();
        $recipes = $user->favoriteRecipes()
            ->orderBy('recipe_user.created_at', 'desc')
            ->paginate(15);
            
        foreach ($recipes as $k => $v) {
            $cdn = getenv('COS_CDN');
            $recipes[$k]['image_src'] = $cdn . $v['image'];
        }
        
        return ApiResponse::success($recipes, '获取收藏列表成功');
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