#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
批量生成食谱数据 - 混合方案

策略：
1. 从 TheMealDB API 获取免费菜谱数据（约 1,600 道，含真实图片）
2. 保留现有 84 条真实中文菜谱
3. 用模板生成补充数据达到目标数量
4. 所有数据存入 import/recipes_full.json 供 Laravel Seeder 导入

使用方法：
    python3 import/generate_recipes.py --target 10000
"""

import os
import json
import time
import random
import urllib.request
import re
from pathlib import Path

# 配置
ROOT = Path(__file__).parent.parent
IMPORT_DIR = ROOT / "import"
PUBLIC_IMG_DIR = ROOT / "public" / "images"
OUTPUT_JSON = IMPORT_DIR / "recipes_full.json"

# TheMealDB API
THEMEALDB_API = "https://www.themealdb.com/api/json/v1/1"

# 请求头
HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "Accept": "application/json",
}

proxy = os.environ.get("https_proxy") or os.environ.get("HTTPS_PROXY")
if proxy:
    OPENER = urllib.request.build_opener(urllib.request.ProxyHandler({"http": proxy, "https": proxy}))
else:
    OPENER = urllib.request.build_opener()


def fetch_api(url):
    try:
        req = urllib.request.Request(url, headers=HEADERS)
        data = OPENER.open(req, timeout=30).read()
        return json.loads(data.decode("utf-8"))
    except Exception as e:
        print(f"  [ERROR] {e}")
        return {}


def download_image(url, dest_path):
    if dest_path.exists() and dest_path.stat().st_size > 1000:
        return True
    try:
        req = urllib.request.Request(url, headers={**HEADERS, "Referer": "https://www.themealdb.com/"})
        data = OPENER.open(req, timeout=20).read()
        if len(data) < 1000:
            return False
        dest_path.write_bytes(data)
        return True
    except:
        return False


def normalize_meal(meal, category_id, user_id=1):
    ingredients = []
    for i in range(1, 21):
        ing_name = meal.get(f"strIngredient{i}", "").strip()
        ing_measure = meal.get(f"strMeasure{i}", "").strip()
        if ing_name:
            ingredients.append({"name": ing_name, "qty": ing_measure if ing_measure else "适量"})

    instructions = meal.get("strInstructions", "")
    directions = []
    if instructions:
        steps = instructions.split("\r\n\r\n")
        for step in steps:
            step = step.strip()
            if step and len(step) > 10:
                directions.append({"description": step})

    if not directions:
        directions = [{"description": "按照传统方法烹饪即可。"}]

    name = meal.get("strMeal", "Unnamed Recipe")
    name = name.replace('"', '').replace("'", "")

    image_url = meal.get("strMealThumb", "")
    image_filename = f"meal_{int(time.time())}_{hash(name) % 100000:06d}.jpg"
    if image_url:
        match = re.search(r'/(\d+)\.jpg', image_url)
        if match:
            image_filename = f"{match.group(1)}.jpg"

    tags = meal.get("strTags", "")
    description = f"{meal.get('strCategory', '')} 菜系，{meal.get('strArea', '')} 风味。"
    if tags:
        description += " 标签: " + tags

    return {
        "name": name,
        "description": description,
        "image": image_filename,
        "image_url": image_url,
        "category_id": category_id,
        "user_id": user_id,
        "ingredients": ingredients,
        "directions": directions[:15],
        "cooking_time": random.randint(20, 90),
        "difficulty": random.choice(["简单", "中等", "困难"]),
        "tags": tags,
    }


def fetch_all_meals():
    all_meals = []

    print("[1/3] 获取分类列表...")
    categories_resp = fetch_api(f"{THEMEALDB_API}/categories.php")
    categories = categories_resp.get("categories", [])
    print(f"  找到 {len(categories)} 个分类")

    print("[2/3] 获取菜谱...")
    fetched_ids = set()

    for category in categories:
        cat_id = category["idCategory"]
        cat_name = category["strCategory"]
        print(f"  获取 {cat_name}...")

        filter_resp = fetch_api(f"{THEMEALDB_API}/filter.php?c={cat_name}")
        meals = filter_resp.get("meals", [])
        print(f"    找到 {len(meals)} 道菜")

        for meal in meals:
            meal_id = meal["idMeal"]
            if meal_id in fetched_ids:
                continue
            fetched_ids.add(meal_id)

            detail_resp = fetch_api(f"{THEMEALDB_API}/lookup.php?i={meal_id}")
            meal_details = detail_resp.get("meals", [{}])[0]

            if meal_details:
                all_meals.append({
                    "recipe": normalize_meal(meal_details, int(cat_id)),
                    "source": "themealdb"
                })

            time.sleep(0.05)

    return all_meals


def generate_chinese_recipes(target_count):
    dish_names = [
        # 川菜
        "麻婆豆腐", "水煮肉片", "回锅肉", "宫保鸡丁", "鱼香肉丝", "夫妻肺片",
        "毛血旺", "辣子鸡", "酸菜鱼", "剁椒鱼头", "开水白菜", "担担面",
        "红烧牛肉面", "水煮鱼", "辣子虾", "馋嘴蛙", "豆花牛柳",
        # 粤菜
        "白切鸡", "烧鹅", "叉烧", "清蒸鲈鱼", "虾饺", "烧卖", "肠粉",
        "煲仔饭", "老火靓汤", "菠萝咕噜肉", "蚝油牛肉", "豉汁蒸排骨",
        "白灼虾", "清远鸡", "深井烧鹅", "广式早茶",
        # 苏菜
        "松鼠桂鱼", "红烧狮子头", "盐水鸭", "南京板鸭", "扬州炒饭",
        "无锡酱排骨", "苏州汤面", "金陵烤鸭",
        # 浙菜
        "东坡肉", "西湖醋鱼", "龙井虾仁", "叫花鸡", "宁波汤圆",
        "杭州馄饨", "绍兴醉鸡", "嘉兴粽子",
        # 湘菜
        "剁椒鱼头", "毛氏红烧肉", "腊味合蒸", "东安子鸡", "口味虾",
        "湘西外婆菜", "永州血鸭", "岳阳鱼丸",
        # 闽菜
        "佛跳墙", "荔枝肉", "沙茶面", "土笋冻", "福州鱼丸",
        "厦门沙茶面", "闽南米粉", "龙岩顿鹅",
        # 徽菜
        "臭鳜鱼", "黄山炖鸽", "毛峰熏鲥鱼", "符离集烧鸡",
        "毛豆腐", "徽州一品锅", "石耳炖鸡",
        # 鲁菜
        "九转大肠", "糖醋鲤鱼", "葱烧海参", "油爆海螺", "孔府一品锅",
        "济南烤鸭", "博山豆腐箱", "泰安三美豆腐",
        # 家常菜
        "番茄炒蛋", "青椒肉丝", "红烧排骨", "清炒时蔬", "蒜蓉西兰花",
        "干煸四季豆", "地三鲜", "土豆烧牛肉", "番茄牛腩", "酸辣土豆丝",
        "蒜蓉空心菜", "清炒豆角", "红烧茄子", "麻婆豆腐",
        # 面食
        "北京烤鸭", "炸酱面", "刀削面", "拉面", "馄饨", "饺子",
        "兰州牛肉面", "新疆拉条子", "武汉热干面", "西安泡馍",
        # 汤类
        "紫菜蛋花汤", "番茄蛋汤", "玉米排骨汤", "萝卜羊肉汤",
        "鸡汤", "排骨汤", "冬瓜排骨汤", "萝卜丝鲫鱼汤",
        # 小吃
        "煎饼果子", "生煎包", "小笼包", "灌汤包", "葱油饼",
        "鲜肉月饼", "粽子", "汤圆", "年糕", "青团",
    ]

    ingredients_pool = [
        {"name": "猪肉", "qty": "300g"}, {"name": "牛肉", "qty": "300g"},
        {"name": "鸡肉", "qty": "300g"}, {"name": "虾仁", "qty": "200g"},
        {"name": "豆腐", "qty": "1块"}, {"name": "鸡蛋", "qty": "2个"},
        {"name": "青椒", "qty": "2个"}, {"name": "番茄", "qty": "2个"},
        {"name": "土豆", "qty": "2个"}, {"name": "茄子", "qty": "2根"},
        {"name": "白菜", "qty": "1颗"}, {"name": "木耳", "qty": "适量"},
        {"name": "香菇", "qty": "5朵"}, {"name": "葱", "qty": "1根"},
        {"name": "姜", "qty": "3片"}, {"name": "蒜", "qty": "3瓣"},
        {"name": "生抽", "qty": "1勺"}, {"name": "老抽", "qty": "1勺"},
        {"name": "料酒", "qty": "1勺"}, {"name": "醋", "qty": "1勺"},
        {"name": "糖", "qty": "1勺"}, {"name": "盐", "qty": "适量"},
        {"name": "胡椒粉", "qty": "适量"}, {"name": "淀粉", "qty": "1勺"},
        {"name": "蚝油", "qty": "1勺"}, {"name": "豆瓣酱", "qty": "1勺"},
        {"name": "干辣椒", "qty": "5个"}, {"name": "花椒", "qty": "1小撮"},
    ]

    step_templates = [
        "食材准备：将主要食材清洗干净，按需要切配备用。",
        "热锅凉油，放入葱姜蒜爆香。",
        "加入主料大火翻炒至变色。",
        "加入调味料，翻炒均匀。",
        "加入适量清水，大火烧开转小火炖煮。",
        "炖煮至食材入味，大火收汁。",
        "装盘，撒上葱花或香菜点缀。",
        "出锅前可淋少许香油增加香味。",
        "注意事项：火候要适中，避免糊锅。",
        "可根据个人口味调整辣度和咸度。",
    ]

    recipes = []
    used_names = set()

    while len(recipes) < target_count:
        name = random.choice(dish_names)
        if name in used_names:
            continue
        used_names.add(name)

        num_ingredients = random.randint(5, 12)
        ingredients = random.sample(ingredients_pool, min(num_ingredients, len(ingredients_pool)))

        num_steps = random.randint(3, 8)
        steps = random.sample(step_templates, min(num_steps, len(step_templates)))

        category_id = random.randint(1, 8)
        cuisines = ["川", "粤", "苏", "闽", "浙", "湘", "徽", "鲁"]

        recipe = {
            "name": name,
            "description": f"{name} 是一道经典 {cuisines[category_id-1]}菜，口味鲜美，做法简单。",
            "image": f"chinese_{hash(name) % 100000:06d}.jpg",
            "image_url": "",
            "category_id": category_id,
            "user_id": 1,
            "ingredients": ingredients,
            "directions": [{"description": s} for s in steps],
            "cooking_time": random.randint(15, 60),
            "difficulty": random.choice(["简单", "中等", "困难"]),
            "tags": "家常菜",
        }
        recipes.append(recipe)

    return recipes


def main(target_count=10000):
    print(f"=== 批量生成食谱数据 ===")
    print(f"目标: {target_count} 条")
    print()

    PUBLIC_IMG_DIR.mkdir(parents=True, exist_ok=True)
    IMPORT_DIR.mkdir(parents=True, exist_ok=True)

    # 加载现有数据
    existing_recipes = []
    if OUTPUT_JSON.exists():
        try:
            with open(OUTPUT_JSON, "r", encoding="utf-8") as f:
                existing_recipes = json.load(f)
            print(f"现有数据: {len(existing_recipes)} 条")
        except:
            pass

    all_recipes = {r["name"]: r for r in existing_recipes}
    print(f"去重后: {len(all_recipes)} 条")

    # 从 TheMealDB 获取
    print("\n[阶段 1] 从 TheMealDB 获取免费菜谱...")
    themealdb_recipes = fetch_all_meals()
    print(f"  获取到 {len(themealdb_recipes)} 条")

    # 添加图片
    print("  下载图片...")
    added_count = 0
    for item in themealdb_recipes:
        recipe = item["recipe"]
        if recipe["name"] not in all_recipes and recipe.get("image_url"):
            img_dest = PUBLIC_IMG_DIR / recipe["image"]
            if download_image(recipe["image_url"], img_dest):
                all_recipes[recipe["name"]] = recipe
                added_count += 1

            if added_count % 100 == 0:
                print(f"    已添加 {added_count} 条...")

    print(f"  成功添加 {added_count} 条")

    # 生成中文菜谱补充
    current_count = len(all_recipes)
    needed = target_count - current_count
    if needed > 0:
        print(f"\n[阶段 2] 生成中文菜谱补充数据...")
        print(f"  需要生成: {needed} 条")

        chinese_recipes = generate_chinese_recipes(needed)
        print(f"  生成了 {len(chinese_recipes)} 条")

        for recipe in chinese_recipes:
            if recipe["name"] not in all_recipes:
                all_recipes[recipe["name"]] = recipe

    # 最终保存
    final_recipes = list(all_recipes.values())
    print(f"\n总计: {len(final_recipes)} 条")

    print(f"\n保存到: {OUTPUT_JSON}")
    with open(OUTPUT_JSON, "w", encoding="utf-8") as f:
        json.dump(final_recipes, f, ensure_ascii=False, indent=2)

    print(f"✓ 完成！")
    print(f"  - 菜谱数据: {OUTPUT_JSON}")
    print(f"  - 图片目录: {PUBLIC_IMG_DIR}")


if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="批量生成食谱数据")
    parser.add_argument("--target", type=int, default=10000, help="目标数量")
    args = parser.parse_args()

    main(target_count=args.target)
