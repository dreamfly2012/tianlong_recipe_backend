import requests
import os
import json
from ddgs import DDGS

# 设置代理（仅当环境变量无效时才需要这样做）
os.environ['HTTP_PROXY'] = "http://127.0.0.1:10809"
os.environ['HTTPS_PROXY'] = "http://127.0.0.1:10809"

def search_image(query: str):
    ddgs = DDGS()
    results = ddgs.images(query=query, max_results=1)
    for r in results:
        return r["image"]

def save_image(image_url: str, filename: str, folder: str = "."):
    """
    根据指定名字保存图片
    参数:
    - image_url: 图片的网络地址
    - filename: 保存的文件名（含扩展名，如 'yuxiangqiezi.jpg'）
    - folder: 保存的目录（默认为当前目录）
    """

    # 确保保存目录存在
    os.makedirs(folder, exist_ok=True)
    file_path = os.path.join(folder, filename)

    try:
        response = requests.get(image_url, timeout=10)
        if response.status_code == 200:
            with open(file_path, "wb") as f:
                f.write(response.content)
            print(f"✅ 图片已保存: {file_path}")
        else:
            print(f"❌ 下载失败，状态码：{response.status_code}")
    except Exception as e:
        print(f"⚠️ 发生错误: {e}")


if __name__ == "__main__":
    ## 读取recipes.json
    with open("recipes.json", "r", encoding="utf-8") as f:
        recipes = json.load(f)
    for recipe in recipes:
        image_url = search_image(recipe["name"])
        save_image(image_url, recipe["image"], "images")



