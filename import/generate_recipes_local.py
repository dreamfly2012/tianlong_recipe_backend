#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
批量生成食谱数据 - 纯本地方案

不依赖外部 API，纯本地生成 10,000 条真实风格的菜谱数据。
包含完整的配料、步骤、烹饪时间、难度、标签等字段。

使用方法：
    python3 import/generate_recipes_local.py --target 10000
"""

import json
import random
import time
from pathlib import Path

ROOT = Path(__file__).parent.parent
IMPORT_DIR = ROOT / "import"
OUTPUT_JSON = IMPORT_DIR / "recipes_full.json"

# 菜名库（按分类组织）
RECIPES_DB = {
    1: {  # 川菜
        "names": [
            "麻婆豆腐", "水煮肉片", "回锅肉", "宫保鸡丁", "鱼香肉丝", "夫妻肺片",
            "毛血旺", "辣子鸡", "酸菜鱼", "剁椒鱼头", "开水白菜", "担担面",
            "红烧牛肉面", "水煮鱼", "辣子虾", "馋嘴蛙", "豆花牛柳", "灯影牛肉",
            "棒棒鸡", "口水鸡", "蒜泥白肉", "泡椒凤爪", "麻辣兔头", "火锅底料",
            "水煮牛肉", "辣子肥肠", "馋嘴蛙", "酸菜豆花", "豆瓣鱼", "鱼香茄子",
            "家常豆腐", "麻婆豆腐", "红油抄手", "伤心凉粉", "甜水面", "钟水饺",
        ],
        "tags": "麻辣,川菜,下饭,香辣"
    },
    2: {  # 粤菜
        "names": [
            "白切鸡", "烧鹅", "叉烧", "清蒸鲈鱼", "虾饺", "烧卖", "肠粉",
            "煲仔饭", "老火靓汤", "菠萝咕噜肉", "蚝油牛肉", "豉汁蒸排骨",
            "白灼虾", "清远鸡", "深井烧鹅", "广式早茶", "陈皮鸭", "桑拿鸡",
            "广州文昌鸡", "梅菜扣肉", "清远白切鸡", "叉烧包", "流沙包",
            "虾饺皇", "凤爪", "蛋挞", "萝卜糕", "肠粉", "粥品",
        ],
        "tags": "清淡,粤菜,鲜香,原味"
    },
    3: {  # 苏菜
        "names": [
            "松鼠桂鱼", "红烧狮子头", "盐水鸭", "南京板鸭", "扬州炒饭",
            "无锡酱排骨", "苏州汤面", "金陵烤鸭", "大煮干丝", "软兜长鱼",
            "平桥豆腐", "蟹粉狮子头", "金陵盐水鸭", "镇江肴肉", "南京鸭血粉丝",
            "扬州酱菜", "苏州炒肉团", "无锡肉骨头", "常州大麻糕", "徐州地锅鸡",
        ],
        "tags": "清淡,苏菜,精致,甜鲜"
    },
    4: {  # 闽菜
        "names": [
            "佛跳墙", "荔枝肉", "沙茶面", "土笋冻", "福州鱼丸",
            "厦门沙茶面", "闽南米粉", "龙岩顿鹅", "醉排骨", "淡糟香螺片",
            "鸡汤氽海蚌", "红糟鸡", "同安封肉", "姜母鸭", "莆田卤面",
            "福州肉燕", "厦门海蛎煎", "泉州鸭肉粥", "漳州卤面", "三明沙县小吃",
        ],
        "tags": "鲜美,闽菜,海鲜,汤鲜"
    },
    5: {  # 浙菜
        "names": [
            "东坡肉", "西湖醋鱼", "龙井虾仁", "叫花鸡", "宁波汤圆",
            "杭州馄饨", "绍兴醉鸡", "嘉兴粽子", "杭州爆鳝鱼", "宁波红膏呛蟹",
            "温州鱼丸", "台州食饼筒", "金华火腿", "衢州烤饼", "丽水薯干百合",
            "绍兴扣肉", "杭州片儿川", "宁波汤圆", "温州鸭舌", "台州糟羹",
        ],
        "tags": "清淡,浙菜,鲜嫩,精致"
    },
    6: {  # 湘菜
        "names": [
            "剁椒鱼头", "毛氏红烧肉", "腊味合蒸", "东安子鸡", "口味虾",
            "湘西外婆菜", "永州血鸭", "岳阳鱼丸", "长沙臭豆腐", "辣椒炒肉",
            "酸辣鸡杂", "腊鸭焖河蚌", "湘西酸肉", "怀化芷江鸭", "常德钵子菜",
            "衡阳鱼头王", "郴州栖凤渡鱼粉", "邵阳猪血丸子", "株洲茶陵灌肠", "湘潭毛氏红烧肉",
        ],
        "tags": "香辣,湘菜,腊味,下饭"
    },
    7: {  # 徽菜
        "names": [
            "臭鳜鱼", "黄山炖鸽", "毛峰熏鲥鱼", "符离集烧鸡", "毛豆腐",
            "徽州一品锅", "石耳炖鸡", "腌笃鲜", "刀板香", "胡适一品锅",
            "李鸿章杂烩", "中和汤", "吴山酥鸡", "符离集烧鸡", "怀远石榴",
            "黄山烧饼", "徽州毛豆腐", "绩溪炒粉丝", "问政山笋", "石鸡炖山药",
        ],
        "tags": "重油重色,徽菜,山野,醇厚"
    },
    8: {  # 鲁菜
        "names": [
            "九转大肠", "糖醋鲤鱼", "葱烧海参", "油爆海螺", "孔府一品锅",
            "济南烤鸭", "博山豆腐箱", "泰安三美豆腐", "德州扒鸡", "把子肉",
            "锅塌豆腐", "奶汤蒲菜", "爆炒腰花", "炸春卷", "济南丸子",
            "周村煮锅", "潍坊萝卜丸子", "临沂炒鸡", "烟台焖子", "青岛海鲜",
        ],
        "tags": "咸鲜,鲁菜,火候,传统"
    },
}

# 通用配料库
INGREDIENTS_POOL = [
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
    {"name": "八角", "qty": "1个"}, {"name": "桂皮", "qty": "1小块"},
    {"name": "香菜", "qty": "适量"}, {"name": "小米辣", "qty": "3个"},
    {"name": "洋葱", "qty": "1个"}, {"name": "胡萝卜", "qty": "1根"},
    {"name": "西兰花", "qty": "1颗"}, {"name": "菜花", "qty": "1颗"},
    {"name": "豆角", "qty": "200g"}, {"name": "冬瓜", "qty": "300g"},
    {"name": "南瓜", "qty": "300g"}, {"name": "丝瓜", "qty": "1根"},
    {"name": "苦瓜", "qty": "1根"}, {"name": "莲藕", "qty": "1节"},
    {"name": "海带", "qty": "适量"}, {"name": "金针菇", "qty": "1包"},
    {"name": "平菇", "qty": "1包"}, {"name": "豆芽", "qty": "200g"},
    {"name": "千张", "qty": "1张"}, {"name": "腐竹", "qty": "5根"},
    {"name": "年糕", "qty": "200g"}, {"name": "方便面", "qty": "1包"},
    {"name": "面条", "qty": "200g"}, {"name": "米饭", "qty": "1碗"},
    {"name": "馒头", "qty": "1个"}, {"name": "饺子皮", "qty": "10张"},
]

# 步骤模板
STEP_TEMPLATES = [
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
    "如果喜欢更入味，可以多加一些汤汁浸泡。",
    "建议搭配米饭食用，更加美味。",
    "煎制时注意控制火候，避免煎糊。",
    "蒸制时要保持大火，确保食材熟透。",
    "炖煮时间根据食材硬度适当调整。",
]

# 描述模板
DESCRIPTION_TEMPLATES = [
    "{name} 是一道经典的 {cuisine} 菜，口感 {taste1}，{taste2}。",
    "{name} 是 {cuisine} 的传统名菜，做法简单，味道 {taste1}。",
    "{name} 属于 {cuisine} 菜系，以 {feature} 著称。",
    "{name} 是 {cuisine} 的代表菜之一，{benefit}。",
    "{name} 口感 {taste1}，{taste2}，非常 {recommend}。",
]

TASTES = ["鲜嫩多汁", "香辣可口", "咸鲜适口", "清淡爽口", "酥烂入味", "外酥里嫩", "Q弹有嚼劲", "鲜美浓郁"]
FEATURES = ["选材讲究", "火候到位", "调味独特", "历史悠久", "做法传统"]
BENEFITS = ["营养丰富", "老少皆宜", "开胃下饭", "滋补养生"]
RECOMMENDS = ["适合家庭聚餐", "宴请宾客首选", "日常家常菜", "新手也能做"]
CUISINES = {1: "川", 2: "粤", 3: "苏", 4: "闽", 5: "浙", 6: "湘", 7: "徽", 8: "鲁"}


def generate_recipe(name, category_id):
    """生成单个菜谱"""
    # 描述
    template = random.choice(DESCRIPTION_TEMPLATES)
    description = template.format(
        name=name,
        cuisine=CUISINES.get(category_id, "中国"),
        taste1=random.choice(TASTES),
        taste2=random.choice(TASTES),
        feature=random.choice(FEATURES),
        benefit=random.choice(BENEFITS),
        recommend=random.choice(RECOMMENDS),
    )

    # 配料（5-12 种）
    num_ingredients = random.randint(5, 12)
    ingredients = random.sample(INGREDIENTS_POOL, min(num_ingredients, len(INGREDIENTS_POOL)))

    # 步骤（3-8 步）
    num_steps = random.randint(3, 8)
    steps = random.sample(STEP_TEMPLATES, min(num_steps, len(STEP_TEMPLATES)))

    return {
        "name": name,
        "description": description,
        "image": f"recipe_{hash(name) % 100000:06d}.jpg",
        "image_url": "",
        "category_id": category_id,
        "user_id": 1,
        "ingredients": ingredients,
        "directions": [{"description": s} for s in steps],
        "cooking_time": random.randint(15, 60),
        "difficulty": random.choice(["简单", "中等", "困难"]),
        "tags": RECIPES_DB.get(category_id, {}).get("tags", "家常菜"),
    }


def main(target_count=10000):
    print(f"=== 批量生成食谱数据 ===")
    print(f"目标: {target_count} 条")
    print()

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

    # 生成新数据
    needed = target_count - len(all_recipes)
    print(f"需要生成: {needed} 条")
    print()

    generated_names = set(r["name"] for r in all_recipes.values())

    # 按分类生成
    for cat_id, cat_data in RECIPES_DB.items():
        if len(all_recipes) >= target_count:
            break

        names = cat_data["names"]
        for name in names:
            if len(all_recipes) >= target_count:
                break
            if name not in generated_names:
                recipe = generate_recipe(name, cat_id)
                all_recipes[name] = recipe
                generated_names.add(name)

    # 如果还不够，随机生成更多
    while len(all_recipes) < target_count:
        cat_id = random.randint(1, 8)
        base_names = RECIPES_DB[cat_id]["names"]
        name = random.choice(base_names) + random.choice(["(改良版)", "(家庭版)", "(快手版)", ""])
        if name not in generated_names:
            recipe = generate_recipe(name, cat_id)
            all_recipes[name] = recipe
            generated_names.add(name)

    # 保存
    final_recipes = list(all_recipes.values())
    print(f"\n总计: {len(final_recipes)} 条")

    print(f"\n保存到: {OUTPUT_JSON}")
    with open(OUTPUT_JSON, "w", encoding="utf-8") as f:
        json.dump(final_recipes, f, ensure_ascii=False, indent=2)

    print(f"✓ 完成！")


if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="批量生成食谱数据")
    parser.add_argument("--target", type=int, default=10000, help="目标数量")
    args = parser.parse_args()

    main(target_count=args.target)
