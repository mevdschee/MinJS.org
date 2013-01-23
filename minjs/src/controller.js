minjs.Controller = new Class(
{ Implements: [Events],
  
  errors: [],
  
  flash: function(type,text)
  { var flash = minjs.target.getElement('#flash');
    if (!flash)
    { flash = new Element('div',{id:'flash'});
      minjs.target.grab(flash, 'top');
    }
    flash.set('text',text);
    flash.set('class',type);
  },
  
  error: function(error,errors)
  { this.flash('error',error)
    if (this.errors) for (var i=0;i<this.errors.length;i++)
    { var hint = minjs.target.getElement('#'+this.errors[i]+'_hint');
      if (hint) hint.hide();
    }
    if (errors) for (var i=0;i<errors.length;i++)
    { var hint = minjs.target.getElement('#'+errors[i]+'_hint');
      if (hint) hint.show();
      else
      { var input = minjs.target.getElement('input[name="'+errors[i]+'"]');
        if (input)
        { hint = new Element('span',{id:errors[i]+'_hint'});
          hint.set('html',' invalid');
          hint.set('class','error');
          hint.inject(input, 'after');
        }
      }
    }
    this.errors = errors;
  },
  
  render: function(view,data,layout)
  { if (!data) data = {};
    if (layout===undefined) layout='default';
    layout = 'layouts/'+layout;
    var template = this.renderTemplate(layout,view,data)
    $(minjs.target).set('html',template.html);
    $(document.head).getFirst('style').set('html',template.css);
    $(minjs.target).getElements('form').each(this.delegate(this.form,[view]));
    $(minjs.target).getElements('a').each(this.delegate(this.anchor,[view]));
    $(minjs.target).getElements('textarea').each(this.delegate(this.editor,[view]));
  },
  
  invoke: function(target,query,parameters,data)
  { var table = query.split('.')[0];
    if (target==minjs.target) minjs.go(query,parameters,data);
    else minjs.partial(target,query,parameters,data);
  },
  
  editor: function(view,t)
  { t.set('value',t.get('value').replace(/<BR>/gi,'\n'));
    if (Browser.ie6 || Browser.ie7) return;
    if (!ace) return;
    var holder = new Element('div');
    var div = new Element('div');
    div.set('class','editor');
    holder.grab(div);
    holder.inject(t, 'after');
    holder.setStyle('width',div.getStyle('width'));
    holder.setStyle('height',div.getStyle('height'));
    t.hide();
    var editor = ace.edit(div);
    editor.setTheme("ace/theme/textmate");
    var className = t.get('class');
    if (className)
    { var codeMode = require("ace/mode/"+className).Mode;
      editor.getSession().setMode(new codeMode());
    }
    editor.getSession().setValue(t.get('value'));
    editor.getSession().setTabSize(2);
    editor.getSession().setUseSoftTabs(true);
    editor.getSession().setUseWrapMode(true);
    editor.getSession().on('change', function(){t.set('value',editor.getSession().getValue())});
    editor.renderer.setHScrollBarAlwaysVisible(false);
  },
 
  anchor: function(view,a)
  { var action = a.get('href');
    if (action.substr(-1)=='#') return;
    action = new URI(action).toRelative(new URI(document.location.href));
    if (action.substr(0,3)!='./#') return;
    action = action.substr(action.indexOf('#')+1);
    action = unescape(action);
    var target = minjs.target;
    var start = action.split('(').shift();
    if (start.indexOf('/')!=-1) return;
    if (!action) action = view.replace('/','.');
    if (a.get('target')) target = a.get('target');
    var query = this.getQuery(action);
    if (!query) query = view.replace('/','.');
    var parameters = this.getParameters(action);
    a.addEvent('click',this.delegate(this.click,[target,a,query,parameters]));
  },
  
  click: function(target,a,query,parameters,event)
  { event.preventDefault(); 
    this.invoke(target,query,parameters);
    return false;
  },
  
  form: function(view,f)
  { var action = f.get('action');
    var enctype = f.get('enctype'); // multipart/form-data
    if (enctype && enctype!='application/x-www-form-urlencoded') return;
    var target = minjs.target;
    if (!action) action = view.replace('/','.');
    if (f.get('target')) target = f.get('target');
    var query = this.getQuery(action);
    if (!query) query = view.replace('/','.');
    var parameters = this.getParameters(action);
    f.getElements('span.error').each(function(e){e.hide();});
    f.addEvent('submit',this.delegate(this.submit,[target,f,query,parameters]));
  },
    
  submit: function(target,f,query,parameters,event)
  { var read = this.readPath;
    var store = this.storePath;
    event.preventDefault(); 
    data = {};
    f.getElements('input, select, textarea', true).each(function(el){
      if (!el.name || el.disabled || el.type == 'submit' || el.type == 'reset' || el.type == 'file') return;
      var value = (el.tagName.toLowerCase() == 'select') ? Element.getSelected(el).map(function(opt){
          return opt.value;
      }) : ((el.type == 'radio' || el.type == 'checkbox') && !el.checked) ? null : el.value;
      Array.from(value).each(function(val){
          if (val !== undefined) {
              if (read(data,el.name)!==undefined)
              { if (typeof read(data,el.name).push == "function") read(data,el.name).push(val);
                else store(data,el.name,[read(data,el.name),val]);
              }  
              else store(data,el.name,val);
          }
      });
    });
    this.invoke(target,query,parameters,data);
    return false;
  },

  storePath: function(parent,path,element)
  { path = path.split('.');
    var id = false;
    for (var i=0;i<path.length;i++)
    { var parentId = id;
      id = path[i];
      if (parentId)
      { if (!parent[parentId])
        { parent[parentId] = {};
        }
        parent = parent[parentId];
      }
    }
    parent[id] = element;
  },
  
  readPath: function(parent,path)
  { path = path.split('.');
    for (var i=0;i<path.length;i++)
    { if (parent[path[i]]===undefined) return undefined;
      parent = parent[path[i]];
    }
    return parent;
  },

  delegate: function(f,p)
  { if (!f) throw 'invalid delegate';
    return function(){return f.apply(this,Array.from(p).concat(Array.from(arguments)))}.bind(this);
  },
  
  getQuery: function(id)
  { var id = id.split('(');
    var query = id.shift();
    return query;
  }.protect(),
  
  getParameters: function(id)
  { var id = id.split('(');
    id.shift();
    var parameters = {};
    if (id.length) 
    { id=id.join('(').split(')'); id.pop(); 
      var js = id.join(')');
      if (js) parameters = new Function('return '+js)();
    }
    return parameters;
  }.protect(),

  escapeStringData: function(data)
  { var htmlEncode = function(string)
    { return new Element('span',{ 'text':string }).get('html');
    }
    var recurse = function(d,f)
    { for (var k in d)
      { switch (typeof(d[k]))
        { case 'object':  recurse(d[k],f); break;
          case 'string':  d[k] = f(d[k]); break;
        }
      }
    }
    recurse(data,htmlEncode);
  	return data;
  },
  
  mergeFunctions: function(functions,data)
  { var r={};
  	for (var k in functions) r[k] = functions[k];
    for (var k in data) r[k] = data[k];
    return r;
  },

  renderTemplate: function(layout,view,viewData) {
    if (!minjs.viewCache) minjs.viewCache={};
    var queries = [];
    if (!minjs.viewCache[view]) queries.push(['view','record:html,css','views.read',{name:view}]);
    if (layout && !minjs.viewCache[layout]) queries.push(['layout','record:html,css','views.read',{name:layout}]);
    var loader = new minjs.Model();
    var data = {}; 
    if (queries.length>0) data = loader.queries(queries);
    if (data.view) minjs.viewCache[view] = data.view;
    if (layout && data.layout) minjs.viewCache[layout] = data.layout;
    if (!minjs.viewCache[view]) throw view+': view not found';
    if (layout && !minjs.viewCache[layout]) throw layout+': view not found';
    var html = this.template(view,minjs.viewCache[view].html,viewData);
    if (layout) html = this.template(layout,minjs.viewCache[layout].html,{content:html});
    var css = minjs.viewCache[view].css;
    if (layout) css+=minjs.viewCache[layout].css;
    return {html:html,css:css};
  },

  // JavaScript micro-templating, similar to John Resig's implementation.
  // Underscore templating handles arbitrary delimiters, preserves whitespace,
  // correctly escapes quotes within interpolated code and supports double
  // percent for templating templates

  template: function(fname,tmpl, data) {
    var interpolate = function(match,code)
    { code = code.replace(/\\'/g, "'");
      var l = tmpl.substr(0,tmpl.indexOf(match)).split("\n").length;
      try{new Function(code)}catch(e){throw fname+':'+l+': '+e;};
      return "');__l="+l+';__p.push(' + code + ",'";
    };
    var evaluate = function(match, code) {
      code = code.replace(/\\'/g, "'").replace(/[\r\n\t]/g, ' ');
      var l = tmpl.substr(0,tmpl.indexOf(match)).split("\n").length;
      try{new Function(code.replace(/[{}]/g,';'))}catch(e){throw fname+':'+l+': '+e;};
      return "');__l="+l+';'+code+"__p.push('";
    };
    var c  = [
      { srch: "\\\\",              rplc: '\\\\'},
      { srch: "'",                 rplc: "\\'"},
      { srch: '<%=([^%]+)%>',      rplc: interpolate },
      { srch: '<% ([^%]+)%>',      rplc: evaluate },
      { srch: '<%%',               rplc: '<%' },
      { srch: "\\r",               rplc: '\\r'},
      { srch: "\\n",               rplc: '\\n'},
      { srch: "\\t",               rplc: '\\t'}
    ];
    data = this.escapeStringData(data);
    data = this.mergeFunctions(minjs.view,data);
    var str = tmpl;
    for(var i=0;i<c.length;i++) str = str.replace(new RegExp(c[i].srch,"g"), c[i].rplc);
    str = 'try{var __l=0,__p=[],print=function(){__p.push.apply(__p,arguments);};' +
    'with(obj||{}){__p.push(\''+str+"');}return __p.join('');}catch(__e){throw '"+fname+":'+__l+': '+__e;}";
    try{return new Function('obj',str)(data)}catch(e){throw fname+':??: '+e;};
  }
  
});
