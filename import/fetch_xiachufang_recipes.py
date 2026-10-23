#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
下厨房菜谱爬虫 v2 - 批量抓取真实菜谱数据

改进：
1. 只爬取 explore 列表页（无需验证码）
2. 从列表页 HTML 提取 JSON-LD 数据
3. 直接下载原始图片（无水印）
4. 断点续传，支持暂停恢复

使用方法：
    python3 import/fetch_xiachufang_recipes.py --target 10000
    python3 import/fetch_xiachufang_recipes.py --resume  # 恢复上次进度
"""

import os
import re
import json
import time
import random
import argparse
import urllib.request
import urllib.parse
from pathlib import Path

# 配置
ROOT = Path(__file__).parent.parent
IMPORT_DIR = ROOT / "import"
PUBLIC_IMG_DIR = ROOT / "public" / "images"
OUTPUT_JSON = IMPORT_DIR / "recipes_full.json"
STATE_FILE = IMPORT_DIR / ".crawler_state_v2.json"

# 请求头
HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
    "Accept-Language": "zh-CN,zh;q=0.9,en;q=0.8",
    "Referer": "https://www.xiachufang.com/",
}

# 代理配置
proxy = os.environ.get("https_proxy") or os.environ.get("HTTPS_PROXY")
if proxy:
    OPENER = urllib.request.build_opener(urllib.request.ProxyHandler({"http": proxy, "https": proxy}))
else:
    OPENER = urllib.request.build_opener()


def fetch_url(url: str, timeout: int = 30) -> str:
    """获取 URL 内容"""
    try:
        req = urllib.request.Request(url, headers=HEADERS)
        data = OPENER.open(req, timeout=timeout).read()
        return data.decode("utf-8", errors="ignore")
    except Exception as e:
        print(f"  [ERROR] {e}")
        return ""


def extract_recipe_ids(html: str) -> list:
    """从 explore 页面提取菜谱 ID"""
    return list(set(re.findall(r'/recipe/(\d+)/', html)))


def parse_jsonld_from_html(html: str) -> dict | None:
    """从 HTML 中解析所有 JSON-LD Recipe 数据"""
    recipes = []
    scripts = re.findall(r'<script[^>]*>(.*?)</script>', html, re.DOTALL)

    for s in scripts:
        if '"@type": "Recipe"' not in s and "'@type': 'Recipe'" not in s:
            continue
        try:
            data = json.loads(s)
            if isinstance(data, dict) and data.get("@type") == "Recipe":
                recipes.append(data)
            elif isinstance(data, list):
                for item in data:
                    if isinstance(item, dict) and item.get("@type") == "Recipe":
                        recipes.append(item)
        except json.JSONDecodeError:
            continue

    return recipes[0] if recipes else None


def normalize_recipe(jsonld: dict) -> dict | None:
    """将 JSON-LD 转换为内部格式"""
    name = jsonld.get("name", "").strip()
    if not name:
        return None

    # 描述
    description = jsonld.get("description", "")
    description = re.sub(r'\n+', '\n', description).strip()

    # 图片
    image_url = jsonld.get("image", "")
    if isinstance(image_url, list):
        image_url = image_url[0] if image_url else ""

    # 提取图片文件名
    image_filename = f"xcf_{int(time.time())}_{hash(name) % 100000:06d}.jpg"
    if image_url:
        match = re.search(r'[/&]([a-f0-9]+)_(\d+)w_(\d+)h\.jpg', image_url)
        if match:
            image_filename = f"{match.group(1)}.jpg"

    # 配料
    ingredients = []
    for ing in jsonld.get("recipeIngredient", []) or []:
        if ing and isinstance(ing, str):
            ing = ing.strip()
            if ing:
                # 尝试分离数量和名称
                match = re.match(r'^([\d两三四五六七八九十百千\d]+[克勺杯碗毫升mlgkg]?[·\-\s]*)?(.+)$', ing)
                if match:
                    qty = match.group(1).strip() if match.group(1) else "适量"
                    ing_name = match.group(2).strip() if match.group(2) else ing
                    ingredients.append({"name": ing_name, "qty": qty})
                else:
                    ingredients.append({"name": ing, "qty": "适量"})

    # 步骤
    directions = []
    instructions = jsonld.get("recipeInstructions", "")
    if instructions:
        if isinstance(instructions, list):
            for step in instructions:
                if isinstance(step, str) and step.strip():
                    directions.append({"description": step.strip()})
                elif isinstance(step, dict):
                    text = step.get("text", step.get("name", ""))
                    if text and str(text).strip():
                        directions.append({"description": str(text).strip()})
        elif isinstance(instructions, str):
            # 按数字分割步骤
            steps = re.split(r'[\n\s]*\d+[.、）)]\s*', instructions)
            for step in steps:
                step = step.strip()
                if step and len(step) > 5:
                    directions.append({"description": step})

    # 回退：从描述中提取步骤
    if not directions and description:
        lines = [l.strip() for l in description.split('\n') if l.strip() and len(l.strip()) > 10]
        for line in lines[:10]:
            directions.append({"description": line})

    return {
        "name": name,
        "description": description[:500] if description else "",
        "image": image_filename,
        "image_url": image_url,
        "ingredients": ingredients[:20],
        "directions": directions[:15],
        "source_id": jsonld.get("@id", ""),
    }


def download_image(url: str, dest_path: Path) -> bool:
    """下载图片"""
    if dest_path.exists() and dest_path.stat().st_size > 1000:
        return True
    try:
        req = urllib.request.Request(url, headers={
            **HEADERS,
            "Referer": "https://www.xiachufang.com/"
        })
        data = OPENER.open(req, timeout=20).read()
        if len(data) < 1000:
            return False
        dest_path.write_bytes(data)
        return True
    except Exception as e:
        return False


def load_state() -> dict:
    """加载状态"""
    if STATE_FILE.exists():
        try:
            return json.loads(STATE_FILE.read_text(encoding="utf-8"))
        except:
            pass
    return {"fetched_ids": set(), "failed_ids": set(), "page": 1, "recipes": {}}


def save_state(state: dict):
    """保存状态"""
    state["fetched_ids"] = list(state.get("fetched_ids", set()))
    state["failed_ids"] = list(state.get("failed_ids", set()))
    STATE_FILE.write_text(json.dumps(state, ensure_ascii=False, indent=2), encoding="utf-8")


def dump_recipes(recipes: dict, output_path: Path):
    """保存菜谱数据"""
    recipe_list = list(recipes.values())
    output_path.write_text(
        json.dumps(recipe_list, ensure_ascii=False, indent=2),
        encoding="utf-8"
    )


def main(target_count: int = 10000, max_pages: int = 500, resume: bool = False):
    print(f"=== 下厨房菜谱爬虫 v2 ===")
    print(f"目标: {target_count} 条")
    print()

    # 确保目录
    PUBLIC_IMG_DIR.mkdir(parents=True, exist_ok=True)
    IMPORT_DIR.mkdir(parents=True, exist_ok=True)

    # 加载状态
    state = load_state()
    fetched_ids = set(state.get("fetched_ids", []))
    failed_ids = set(state.get("failed_ids", []))
    all_recipes = state.get("recipes", {})
    current_page = state.get("page", 1)

    if resume and all_recipes:
        print(f"恢复模式：已加载 {len(all_recipes)} 条现有数据")
    else:
        print(f"全新爬取")

    print(f"已爬取: {len(fetched_ids)} 条")
    print(f"失败: {len(failed_ids)} 条")
    print(f"从第 {current_page} 页继续...")
    print()

    # 爬取 explore 页面
    page = current_page
    while len(all_recipes) < target_count and page <= max_pages:
        url = f"https://www.xiachufang.com/explore/?page={page}"
        print(f"[Page {page}] Fetching {url} ...")

        html = fetch_url(url)
        if not html:
            print(f"  [WARN] Failed, skipping")
            page += 1
            time.sleep(2)
            continue

        recipe_ids = extract_recipe_ids(html)
        new_ids = [rid for rid in recipe_ids if rid not in fetched_ids and rid not in failed_ids]

        if not new_ids:
            print(f"  No new IDs")
            page += 1
            time.sleep(random.uniform(1, 2))
            continue

        print(f"  Found {len(new_ids)} new recipes")

        # 解析每页的 JSON-LD 数据
        # explore 页面可能包含多个 Recipe JSON-LD
        jsonld_recipes = parse_jsonld_from_html(html)

        if jsonld_recipes:
            recipe = normalize_recipe(jsonld_recipes)
            if recipe:
                # 下载图片
                if recipe.get("image_url") and recipe.get("image"):
                    img_dest = PUBLIC_IMG_DIR / recipe["image"]
                    if download_image(recipe["image_url"], img_dest):
                        print(f"  ✓ {recipe['name'][:20]}...")
                    else:
                        print(f"  ⚠ {recipe['name'][:20]}... (img fail)")
                else:
                    print(f"  ✓ {recipe['name'][:20]}...")

                all_recipes[recipe["name"]] = recipe
                fetched_ids.add(jsonld_recipes.get("@id", "").replace("/recipe/", "").replace("/", "").strip())
        else:
            print(f"  ⚠ No JSON-LD data found")

        # 保存进度
        state["fetched_ids"] = fetched_ids
        state["failed_ids"] = failed_ids
        state["recipes"] = all_recipes
        state["page"] = page + 1
        save_state(state)

        # 定期保存完整数据
        if page % 10 == 0:
            dump_recipes(all_recipes, OUTPUT_JSON)
            print(f"  [PROGRESS] Saved {len(all_recipes)} recipes")

        page += 1
        time.sleep(random.uniform(0.5, 1.5))

    # 最终保存
    dump_recipes(all_recipes, OUTPUT_JSON)

    print()
    print(f"=== 完成 ===")
    print(f"成功: {len(all_recipes)} 条")
    print(f"数据: {OUTPUT_JSON}")
    print(f"图片: {PUBLIC_IMG_DIR}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="下厨房菜谱爬虫")
    parser.add_argument("--target", type=int, default=10000, help="目标数量")
    parser.add_argument("--max-pages", type=int, default=500, help="最大页数")
    parser.add_argument("--resume", action="store_true", help="恢复上次进度")
    args = parser.parse_args()

    main(target_count=args.target, max_pages=args.max_pages, resume=args.resume)
