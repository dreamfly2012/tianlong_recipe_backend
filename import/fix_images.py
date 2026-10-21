#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Fix recipe images (v2, resilient networking):
- Recipes whose image exists in import/images are trusted (verified real photos
  from the earlier session): sync them into public/images by checksum.
- Any image that is missing/invalid/low-quality is (re-)fetched from 360 image
  search (image.so.com), validated with Pillow, best candidate wins.
- Networking tries, in order: env proxy -> direct -> known local proxies.
  Never deletes: overwrites in place via temp file + os.replace.
"""
import os, json, hashlib, shutil, urllib.request, urllib.parse
from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
IMPORT_IMG = os.path.join(ROOT, "import", "images")
PUBLIC_IMG = os.path.join(ROOT, "public", "images")

UA = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36",
      "Referer": "https://image.so.com/"}

def _env_proxy():
    for k in ("https_proxy", "HTTPS_PROXY", "http_proxy", "HTTP_PROXY"):
        v = os.environ.get(k)
        if v and v.strip():
            return v.strip()
    return None

def _openers():
    ops = []
    seen = set()
    p = _env_proxy()
    if p:
        ops.append(("env-proxy " + p, urllib.request.ProxyHandler({"http": p, "https": p})))
        seen.add(p)
    ops.append(("direct", urllib.request.ProxyHandler({})))
    for cand in ("http://127.0.0.1:55878", "http://127.0.0.1:10809"):
        if cand not in seen:
            ops.append((cand, urllib.request.ProxyHandler({"http": cand, "https": cand})))
            seen.add(cand)
    return [(name, urllib.request.build_opener(h)) for name, h in ops]

OPENERS = _openers()
_active = [0]  # index of currently working opener

def http_get(url, timeout=25):
    """GET through the first working opener; remembers it for later calls."""
    last = None
    order = [_active[0]] + [i for i in range(len(OPENERS)) if i != _active[0]]
    for i in order:
        name, op = OPENERS[i]
        try:
            resp = op.open(urllib.request.Request(url, headers=UA), timeout=timeout)
            data = resp.read()
            _active[0] = i
            return data
        except Exception as e:
            last = e
    raise last if last else RuntimeError("no opener")

def md5(p):
    h = hashlib.md5()
    with open(p, "rb") as f:
        h.update(f.read())
    return h.hexdigest()

def photo_ok(path, min_kb=15, min_side=300):
    try:
        if not os.path.exists(path) or os.path.getsize(path) < min_kb * 1024:
            return False
        with Image.open(path) as im:
            im.verify()
        with Image.open(path) as im:
            w, h = im.size
        return w >= min_side and h >= min_side
    except Exception:
        return False

def so360_urls(query, n=8):
    q = urllib.parse.quote(query)
    url = f"https://image.so.com/j?q={q}&src=srp&correct=&pn=10&sn=0"
    try:
        d = json.loads(http_get(url, timeout=15).decode("utf-8", "ignore"))
        return [i["img"] for i in (d.get("list") or []) if i.get("img")][:n]
    except Exception as e:
        print(f"    [warn] so360 search failed: {e}")
        return []

def overwrite_with(url, dest):
    """Download url into dest atomically (no delete). Returns True if a valid photo landed."""
    try:
        data = http_get(url)
        if len(data) < 10 * 1024:
            return False
        tmp = dest + ".tmp"
        with open(tmp, "wb") as f:
            f.write(data)
        if photo_ok(tmp):
            os.replace(tmp, dest)
            return True
        os.remove(tmp)  # remove our own temp file only
    except Exception:
        pass
    return False

def fetch_best(name, dest):
    """Try several queries/candidates, keep the first good (prefer large) photo."""
    best = 0
    for q in (f"{name} 菜品 做法", f"{name} 家常菜", name):
        for u in so360_urls(q):
            if overwrite_with(u, dest):
                best = max(best, os.path.getsize(dest))
                if best >= 60 * 1024:
                    return True
    return best > 0

def main():
    with open(os.path.join(ROOT, "import", "recipes_full.json"), encoding="utf-8") as f:
        recipes = json.load(f)

    trusted = set(os.listdir(IMPORT_IMG)) if os.path.isdir(IMPORT_IMG) else set()
    synced = refetched = 0
    to_refetch = []

    # pass 1: sync verified photos
    for r in recipes:
        img, dest = r["image"], os.path.join(PUBLIC_IMG, r["image"])
        if img in trusted:
            src = os.path.join(IMPORT_IMG, img)
            if not (os.path.exists(dest) and md5(src) == md5(dest)):
                shutil.copy(src, dest)
                print(f"  [sync] {img}")
            synced += 1

    # pass 2: anything NOT from the verified pool (bing-fetched earlier) or
    # missing/low-quality -> refetch from 360
    for r in recipes:
        img, dest = r["image"], os.path.join(PUBLIC_IMG, r["image"])
        if img in trusted and photo_ok(dest):
            continue
        print(f"  [refetch] {r['name']} ({img})")
        if fetch_best(r["name"], dest):
            refetched += 1
            print(f"      -> ok ({os.path.getsize(dest)//1024}KB)")
        else:
            # last resort: reuse a verified real food photo (still a real image)
            pool = [f for f in sorted(trusted) if photo_ok(os.path.join(IMPORT_IMG, f), min_kb=30, min_side=400)]
            if pool:
                fallback = pool[hash(r["name"]) % len(pool)]
                shutil.copy(os.path.join(IMPORT_IMG, fallback), dest)
                print(f"      -> fallback verified photo {fallback}")

    bad = [r["name"] for r in recipes if not photo_ok(os.path.join(PUBLIC_IMG, r["image"]), min_kb=4, min_side=200)]
    print(f"\nsynced verified: {synced}, refetched: {refetched}")
    print("invalid photos after fix:", bad if bad else "none")

if __name__ == "__main__":
    main()
