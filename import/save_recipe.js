import axios from 'axios';
import FormData from 'form-data';
import fs from 'fs';

const recipes = JSON.parse(fs.readFileSync('./recipes.json', 'utf8'));
const API_URL = 'http://localhost:8000/api/recipes';
const TOKEN = '1|DyUvWlmKjm4txBiLSOdk6Nx2UndeR7WW2mZ2Kygfa58d7a7d'; // 替换为你的实际token

async function uploadRecipe(recipes) {
  const uploadPromises = recipes.map(async (recipe) => {
    const form = new FormData();
    form.append('name', recipe.name);
    form.append('description', recipe.description);
    form.append('category_id', recipe.category_id);
    // 如果有图片文件，上传本地图片，否则只传文件名
    try {
      form.append('image', fs.createReadStream(`./images/${recipe.image}`));
    } catch (e) {
      form.append('image', recipe.image);
    }
    recipe.ingredients.forEach((item, i) => {
      form.append(`ingredients[${i}][name]`, item.name);
      form.append(`ingredients[${i}][qty]`, item.qty);
    });
    recipe.directions.forEach((item, i) => {
      form.append(`directions[${i}][description]`, item.description);
    });

    try {
      const res = await axios.post(API_URL, form, {
        headers: {
          ...form.getHeaders(),
          Authorization: `Bearer ${TOKEN}`,
        },
        maxBodyLength: Infinity,
      });
      console.log(`成功导入: ${recipe.name}`);
    } catch (err) {
      console.error(`导入失败: ${recipe.name}`, err.response?.data || err.message);
    }
  });

  await Promise.all(uploadPromises);
}

(async () => {
  await uploadRecipe(recipes);
})();
