minjs.view = {};
minjs.view.merge = function(o,o2)
{ var r={};
  for (var k in o) r[k]= o[k];
  for (var k in o2) r[k]= o2[k];
  return r;
}
minjs.view.link_to = function(text, href, options)
{ if (!text) var text = href;
  if (!options) var options = {};
  options.text = text;
  options.href = href;
  return new Element('span').grab(new Element('a', options)).get('html');
}
minjs.view.link_fn = function(params, text, fn, options)
{ if (!text) var text = fn;
  if (!options) var options = {};
  options.text = text;
  options.href = '#'+fn;
  if (params) options.href += '('+JSON.encode(params)+')';
  return new Element('span').grab(new Element('a', options)).get('html');
}
minjs.view.order_by = function(params, text, options)
{ return this.link_fn(this.merge(params,{order:text}), text, '')
}
minjs.view.next = function(params, text, options)
{ if (!params.limit) return '';
  var offset = params.offset;
  if (!offset) offset = params.limit
  else offset+=params.limit;
  return this.link_fn(this.merge(params,{offset:offset}), text, '')
}
minjs.view.prev = function(params, text, options)
{ if (!params.limit) return '';
  var offset = params.offset;
  if (!offset) offset = 0;
  else offset-=params.limit;
  if (offset<0) offset = 0;
  return this.link_fn(this.merge(params,{offset:offset}), text, '')
}
minjs.view.paging = function(params, len)
{ if (!params.limit) return '';
  var r='';
  r+=params.offset?this.prev(params,'prev'):'prev';
  r+=' page '+(params.offset?Math.floor(params.offset/params.limit)+1:1)+' ';
  r+=len==params.limit?this.next(params,'next'):'next';
  return r;
}
minjs.view.remove_table_row = function(text,options)
{ if (!options) var options = {}
  options.text = text;
  options.href = '#';
  options.onclick = 'this.getParent("tr").dispose();return false;';
  return new Element('span').grab(new Element('a', options)).get('html');
}
minjs.view.add_table_row = function(text,options)
{ if (!options) var options = {}
  options.text = text;
  options.href = '#';
  options.onclick = 'var html=this.getParent("table").get("html").split("\\<\\!\\-\\-")[1].split("\\-\\-\\>")[0];';
  options.onclick+= 'html = html.substring(html.indexOf("<"),html.lastIndexOf(">")+1);';
  options.onclick+= 'var e = (new Element("table")).set("html",html); e.getElement("tr").inject(this.getParent("tr"),"before");';
  options.onclick+= 'return false;';
  return new Element('span').grab(new Element('a', options)).get('html');
}
minjs.view.htmlEncode = function(string)
{ return new Element('span',{ 'text':string }).get('html');
}
minjs.view.htmlDecode = function(string)
{ return new Element('span',{ 'html':string }).get('text');
}
minjs.view.file = function(options)
{ if (!options) var options = {}
  if (!options.name) options.name = 'file';
  if (!options.type) options.type = 'single';
  if (!options.style) options.style = 'height: 1.6em; border:0; margin:0; padding:0;';
  var type = options.type;
  delete options.type;
  var name = options.name;
  options.src = 'lib/file.php?name='+encodeURIComponent(name)+'&type='+encodeURIComponent(type);
  var e = new Element('span').grab(new Element('iframe', options));
  return e.grab(new Element('br')).get('html');
}
