export function toMultipartedForm(form, mode) {
  // 使用对象展开运算符浅拷贝，保留File对象
  const temp = { ...form };

  if (mode === 'edit') {
    // 字符串是服务端已有的图片文件名，无需重复上传
    if (typeof temp.image === 'string') {
      delete temp.image;
    } else if (temp.image === null || temp.image === undefined) {
      // null 表示用户在编辑页删除了图片，FormData 无法表达“清空”，改用显式标记
      delete temp.image;
      temp.image_removed = 1;
    }
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
            let value = obj[property];
            // null/undefined 不参与提交，否则 FormData 会把它序列化成 "null" 字符串
            if(value === null || value === undefined) {
                continue;
            }
            if(value instanceof Array) {
                for (var i = 0; i < value.length; i++) {
                    objectToFormData(value[i], fd, `${property}[${i}]`);
                }
            } else if(typeof value === 'object' && !(value instanceof File)) {
                objectToFormData(value, fd, property);
            } else {
                fd.append(formKey, value);
            }
        }
    }
    return fd
}
