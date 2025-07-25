export function toMultipartedForm(form, mode) {
  // 使用对象展开运算符浅拷贝，保留File对象
  const temp = { ...form };
  
  // 仅在编辑模式且image是字符串URL时才删除image字段
  if(mode === 'edit' && typeof form.image === 'string') {
    delete temp.image;
  }
  
  return objectToFormData(temp);
}

function objectToFormData(obj, form, namespace) {
    let fd = form || new FormData()
    let formKey
    for(var property in obj) {
        if(obj.hasOwnProperty(property)) {
            if(namespace) {
                formKey = namespace + '[' + property + ']';
            } else {
                formKey = property;
            }
            if(obj[property] instanceof Array) {
                for (var i = 0; i < obj[property].length; i++) {
                    objectToFormData(obj[property][i], fd, `${property}[${i}]`);
                }
            } else if(typeof obj[property] === 'object' && !(obj[property] instanceof File)) {
                objectToFormData(obj[property], fd, property);
            } else {
                fd.append(formKey, obj[property]);
            }
        }
    }
    return fd
}