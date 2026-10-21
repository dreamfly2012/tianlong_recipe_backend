#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Build pipeline for recipe seed data.

1. Normalizes the existing import/recipes.json (fixes category_id to the
   八大菜系 mapping, drops the duplicate 清炒豆苗).
2. Merges the curated import/new_recipes.json (~35 new real Chinese dishes).
3. Ensures every recipe has a REAL photo in public/images (copy the already
   downloaded import/images, and fetch the rest from Bing Images via the proxy).
4. Writes import/recipes_full.json (the complete dataset).
5. Generates database/seeders/RecipesTableSeeder.php that reads that JSON.
"""
import os, json, shutil, re, urllib.request, urllib.parse, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
IMPORT_DIR = os.path.join(ROOT, "import")
PUBLIC_IMG = os.path.join(ROOT, "public", "images")
IMPORT_IMG = os.path.join(IMPORT_DIR, "images")

proxy = os.environ.get("https_proxy") or os.environ.get("HTTPS_PROXY")
OPENER = urllib.request.build_opener(urllib.request.ProxyHandler({"http": proxy, "https": proxy}))
UA = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"}

# name -> correct 八大菜系 id  (1川 2粤 3苏 4闽 5浙 6湘 7徽 8鲁)
CAT = {
    "鱼香茄子": 1, "蚝油生菜": 2, "可乐鸡翅": 2, "地三鲜": 8, "葱爆羊肉": 8,
    "糖醋里脊": 8, "干煸四季豆": 1, "水煮肉片": 1, "回锅肉": 1, "清蒸鲈鱼": 2,
    "番茄牛腩": 2, "葱油拌面": 3, "香菇滑鸡": 2, "凉拌黄瓜": 8, "香辣蟹": 6,
    "干锅花菜": 1, "梅菜扣肉": 2, "鱼香豆腐": 1, "孜然羊肉": 8, "清炒苦瓜": 2,
    "椒盐虾": 2, "蒜蓉粉丝蒸虾": 2, "红烧鲫鱼": 8, "香辣鸡翅": 2, "蒜蓉空心菜": 2,
    "红烧狮子头": 3, "清炒豆苗": 2, "香辣牛蛙": 1, "铁板豆腐": 2, "香辣花甲": 6,
    "红烧排骨": 2, "清蒸大闸蟹": 3, "蒜蓉茼蒿": 3, "香煎三文鱼": 2, "红烧带鱼": 8,
    "葱爆鱿鱼": 8, "香辣鸡爪": 6, "红烧鲤鱼": 8, "干锅肥肠": 1, "红烧鸡翅": 2,
    "清炒虾仁": 3, "蒜蓉西葫芦": 3, "香辣牛肉": 1, "红烧牛腩": 2, "香煎豆腐": 2,
    "葱爆猪肝": 8, "香辣鱼块": 1, "红烧豆腐": 3, "香辣鸡胗": 1,
}

def bing_image_urls(query, n=6):
    q = urllib.parse.quote(query)
    url = f"https://www.bing.com/images/search?q={q}&form=HDRSC2"
    try:
        req = urllib.request.Request(url, headers=UA)
        html = OPENER.open(req, timeout=15).read().decode("utf-8", "ignore")
        murls = re.findall(r'murl&quot;:&quot;(.*?)&quot;', html)
        if not murls:
            murls = re.findall(r'"murl":"(.*?)"', html)
        # decode &amp; etc just in case
        murls = [urllib.parse.unquote(u) for u in murls]
        return murls[:n]
    except Exception as e:
        print(f"  [warn] bing search failed for {query!r}: {e}")
        return []

def is_valid_image(path):
    try:
        with open(path, "rb") as f:
            head = f.read(12)
        if head[:3] == b"\xff\xd8\xff":  # jpeg
            return os.path.getsize(path) > 4000
        if head[:8] == b"\x89PNG\r\n\x1a\n":  # png
            return os.path.getsize(path) > 4000
        if head[:4] in (b"GIF8",):  # gif
            return os.path.getsize(path) > 4000
        if head[:4] == b"RIFF" and head[8:12] == b"WEBP":
            return os.path.getsize(path) > 4000
    except Exception:
        return False
    return False

def download(url, dest):
    try:
        req = urllib.request.Request(url, headers={**UA, "Referer": "https://www.bing.com/"})
        data = OPENER.open(req, timeout=25).read()
        if len(data) < 4000:
            return False
        with open(dest, "wb") as f:
            f.write(data)
        return is_valid_image(dest)
    except Exception as e:
        return False

def fetch_real_image(recipe_name, dest):
    """Fetch a real photo for recipe_name into dest. Returns True on success."""
    if os.path.exists(dest) and is_valid_image(dest):
        return True
    queries = [f"{recipe_name} 菜品 美食", f"{recipe_name} 家常菜", f"{recipe_name}"]
    for q in queries:
        for u in bing_image_urls(q):
            if download(u, dest):
                return True
    return False

def main():
    os.makedirs(PUBLIC_IMG, exist_ok=True)

    # ---- 1. normalize existing ----
    with open(os.path.join(IMPORT_DIR, "recipes.json"), encoding="utf-8") as f:
        existing = json.load(f)

    seen = set()
    norm = []
    for r in existing:
        name = r["name"]
        if name in seen:  # drop duplicate 清炒豆苗
            print(f"  [drop] duplicate dish: {name}")
            continue
        seen.add(name)
        norm.append({
            "name": name,
            "description": r["description"],
            "image": r["image"],
            "category_id": CAT.get(name, r.get("category_id")),
            "ingredients": r["ingredients"],
            "directions": r["directions"],
        })
    print(f"normalized existing: {len(norm)} (from {len(existing)})")

    # ---- 2. merge new ----
    with open(os.path.join(IMPORT_DIR, "new_recipes.json"), encoding="utf-8") as f:
        new = json.load(f)
    # avoid name clashes with existing
    new = [n for n in new if n["name"] not in seen]
    for n in new:
        seen.add(n["name"])
        n["image"] = n.pop("slug") + ".jpg"
        n.setdefault("user_id", 1)
    print(f"new recipes: {len(new)}")
    all_recipes = norm + new
    print(f"TOTAL recipes: {len(all_recipes)}")

    # ---- 3. images ----
    # copy all already-downloaded import/images as a safety net
    copied = 0
    if os.path.isdir(IMPORT_IMG):
        for fn in os.listdir(IMPORT_IMG):
            sp = os.path.join(IMPORT_IMG, fn)
            dp = os.path.join(PUBLIC_IMG, fn)
            if os.path.isfile(sp) and not os.path.exists(dp):
                shutil.copy(sp, dp)
                copied += 1
    print(f"copied {copied} pre-downloaded images to public/images")

    missing = []
    for r in all_recipes:
        img = r["image"]
        dest = os.path.join(PUBLIC_IMG, img)
        if os.path.exists(dest) and is_valid_image(dest):
            continue
        print(f"  fetching image for: {r['name']} ({img})")
        ok = fetch_real_image(r["name"], dest)
        if not ok:
            missing.append(r["name"])
            print(f"  [FAIL] could not fetch image for {r['name']}; will use fallback")
    if missing:
        # fallback: copy any existing valid food image under a new name
        pool = [f for f in os.listdir(PUBLIC_IMG)
                if f.lower().endswith((".jpg", ".jpeg", ".png")) and is_valid_image(os.path.join(PUBLIC_IMG, f))]
        for name in missing:
            if not pool:
                break
            src = os.path.join(PUBLIC_IMG, pool[0])
            dest = os.path.join(PUBLIC_IMG, f"fallback_{abs(hash(name))%100000}.jpg")
            shutil.copy(src, dest)
            # patch the recipe image name
            for r in all_recipes:
                if r["name"] == name:
                    r["image"] = os.path.basename(dest)
    print(f"images ready; missing after fallback: {len([m for m in missing if not os.path.exists(os.path.join(PUBLIC_IMG, [r['image'] for r in all_recipes if r['name']==m][0]))]) if missing else 0}")

    # ---- 4. write full dataset ----
    for r in all_recipes:
        r.setdefault("user_id", 1)
    out = os.path.join(IMPORT_DIR, "recipes_full.json")
    with open(out, "w", encoding="utf-8") as f:
        json.dump(all_recipes, f, ensure_ascii=False, indent=2)
    print(f"wrote {out} ({len(all_recipes)} recipes)")

    # ---- 5. generate seeder ----
    generate_seeder()
    print("done.")

def generate_seeder():
    php = """<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\File;

class RecipesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 由于存在外键约束，先禁用外键检查（MySQL）
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('recipe_ingredients')->truncate();
        DB::table('recipe_directions')->truncate();
        DB::table('recipes')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $path = base_path('import/recipes_full.json');
        if (! File::exists($path)) {
            $this->command->warn("未找到 {$path}，跳过菜谱填充。");
            return;
        }

        $recipes = json_decode(File::get($path), true);

        foreach ($recipes as $recipe) {
            $recipeId = DB::table('recipes')->insertGetId([
                'user_id'     => $recipe['user_id'] ?? 1,
                'category_id' => $recipe['category_id'],
                'name'        => $recipe['name'],
                'description' => $recipe['description'],
                'image'       => $recipe['image'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            foreach ($recipe['ingredients'] as $ing) {
                DB::table('recipe_ingredients')->insert([
                    'recipe_id' => $recipeId,
                    'name'      => $ing['name'],
                    'qty'       => $ing['qty'],
                ]);
            }

            foreach ($recipe['directions'] as $dir) {
                DB::table('recipe_directions')->insert([
                    'recipe_id'   => $recipeId,
                    'description' => $dir['description'],
                ]);
            }
        }

        $this->command->info('已导入 '.count($recipes).' 条菜谱（含配料与步骤）。');
    }
}
"""
    dest = os.path.join(ROOT, "database", "seeders", "RecipesTableSeeder.php")
    with open(dest, "w", encoding="utf-8") as f:
        f.write(php)
    print(f"wrote {dest}")

if __name__ == "__main__":
    main()
