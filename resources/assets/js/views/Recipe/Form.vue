<template>
	<div class="recipe__show">
		<div class="recipe__header">
			<h3>{{action}} 菜谱</h3>
			<div>
				<button class="btn btn__primary" @click="save" :disabled="isProcessing">保存</button>
				<button class="btn" @click="$router.back()" :disabled="isProcessing">取消</button>
			</div>
		</div>
		<div class="recipe__row">
			<div class="recipe__image">
				<div class="recipe__box">
					<image-upload v-model="form.image"></image-upload>
					<small class="error__control" v-if="error.image">{{error.image[0]}}</small>
				</div>
			</div>
			<div class="recipe__details">
				<div class="recipe__details_inner">
					<div class="form__group">
					    <label>菜名</label>
					    <input type="text" class="form__control" v-model="form.name">
					    <small class="error__control" v-if="error.name">{{error.name[0]}}</small>
					</div>
					<div class="form__group">
					    <label>分类</label>
						<select v-model="form.parent_id" @change="selectCategory">
							<option v-for="item in parent_categories" v-bind:key="item.category_id" :value="item.category_id">{{item.name}}</option>
						</select>
						<select v-model="form.category_id">
							<option v-for="item in categories" v-bind:key="item.category_id" :value="item.category_id">{{item.name}}</option>
						</select>
					   
					    <small class="error__control" v-if="error.name">{{error.name[0]}}</small>
					</div>
					<div class="form__group">
					    <label>描述</label>
					    <textarea class="form__control form__description" v-model="form.description"></textarea>
					    <small class="error__control" v-if="error.description">{{error.description[0]}}</small>
					</div>
				</div>
			</div>
		</div>
		<div class="recipe__row">
			<div class="recipe__ingredients">
				<div class="recipe__box">
					<h3 class="recipe__sub_title">材料</h3>
					<div v-for="(ingredient, index) in form.ingredients" class="recipe__form">
						<input type="text" class="form__control" v-model="ingredient.name"
							:class="[error[`ingredients.${index}.name`] ? 'error__bg' : '']">
						<input type="text" class="form__control form__qty" v-model="ingredient.qty"
							:class="[error[`ingredients.${index}.qty`] ? 'error__bg' : '']">
						<button @click="remove('ingredients', index)" class="btn btn__danger">&times;</button>
					</div>
					<button @click="addIngredient" class="btn">Add Ingredient</button>
				</div>
			</div>
			<div class="recipe__directions">
				<div class="recipe__directions_inner">
					<h3 class="recipe__sub_title">步骤</h3>
					<div v-for="(direction, index) in form.directions" class="recipe__form">
						<textarea class="form__control form__margin" v-model="direction.description"
							:class="[error[`directions.${index}.description`] ? 'error__bg' : '']"
							></textarea>
						<button @click="remove('directions', index)" class="btn btn__danger">&times;</button>
					</div>
					<button @click="addDirection" class="btn">Add Direction</button>
				</div>
			</div>
		</div>
	</div>
</template>
<script type="text/javascript">
	import Vue from 'vue'
	import Flash from '../../helpers/flash'
	import { get, post } from '../../helpers/api'
	import { toMulipartedForm } from '../../helpers/form'
	import ImageUpload from '../../components/ImageUpload.vue'

	export default {
		components: {
			ImageUpload
		},
		data() {
			return {
				form: {
					ingredients: [],
					directions: []
				},
				parent_categories:[],
				categories:[],
				error: {},
				isProcessing: false,
				initializeURL: `/api/recipes/create`,
				storeURL: `/api/recipes`,
				categoryURL: `/api/recipe.category`,
				action: '创建'
			}
		},
		created() {
			if(this.$route.meta.mode === 'edit') {
				this.initializeURL = `/api/recipes/${this.$route.params.id}/edit`
				this.storeURL = `/api/recipes/${this.$route.params.id}?_method=PUT`
				this.action = '更新'
			}
			get(this.initializeURL)
				.then((res) => {
					res.data.form.parent_id = 1
					Vue.set(this.$data, 'form', res.data.form)
					Vue.set(this.$data, 'parent_categories', res.data.cateories)
				})
			get(this.categoryURL+'?parent_id=1')
				.then((res)=>{
					Vue.set(this.$data, 'categories', res.data.data)
					this.form.category_id = res.data.data[0].category_id
			    })	
		},
		methods: {
			selectCategory(value){
				get(this.categoryURL+'?parent_id=' + value.target.value)
				.then((res)=>{
					Vue.set(this.$data, 'categories', res.data.data)
					this.form.category_id = res.data.data[0].category_id
			    })
            },
			save() {
				const form = toMulipartedForm(this.form, this.$route.meta.mode)
				post(this.storeURL, form)
				    .then((res) => {
				        if(res.data.saved) {
				            Flash.setSuccess(res.data.message)
				            this.$router.push(`/recipes/${res.data.id}`)
				        }
				        this.isProcessing = false
				    })
				    .catch((err) => {
				        if(err.response.status === 422) {
				            this.error = err.response.data
				        }
				        this.isProcessing = false
				    })
			},
			addDirection() {
				this.form.directions.push({
					description: ''
				})
			},
			addIngredient() {
				this.form.ingredients.push({
					name: '',
					qty: ''
				})
			},
			remove(type, index) {
				if(this.form[type].length > 1) {
					this.form[type].splice(index, 1)
				}
			}
		}
	}
</script>
