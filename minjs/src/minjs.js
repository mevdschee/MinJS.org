var minjs = new new Class({

  target: null,
    
  // asynchronous: should be called as last function in a method, does not return a value
  go: function(query,parameters,data)
  { var f = this.loadCall(query,parameters,data);
    if (!this.target) this.setTarget($(document.body).getFirst('div'));
    if (!data && this.browser) this.browser.go(query,parameters,data);
    try
    { f(parameters,data);
      if (!data && this.browser) this.browser.updateAddressBar();
    }
    catch (failure)
    { console.log(failure);
    }
  },
  
  // asynchronous: should be called as last function in a method, does not return a value
  partial: function(target,query,parameters,data)
  { var f = this.loadCall(query,parameters,data);
    var oldTarget = this.setTarget(target);
    f(parameters,data);
    this.setTarget(oldTarget);
  },
  
  setTarget: function(target)
  { var oldTarget = this.target;
    if (typeof target == 'string') target = document.id(target);
    if (!target) throw 'target not found';
    this.target = target;
    return oldTarget;
  },
  
  loadController: function(controller)
  { var model = controller.singularize();
    // load controller
    if (this[controller] === undefined)
    { try
      { var loader = new minjs.Model();
        new Function(loader.query('value:data','controllers.read',{name:controller}))();
      }
      catch(e)
      { console.log('@mysql/controllers/'+controller+'/data/javascript');
        throw e;
      }
    }
    // load model
    if (this[controller][model] === undefined)
    { try
      { var loader = new minjs.Model();
        new Function(loader.query('value:data','models.read',{name:model}))();
      }
      catch(e)
      { console.log('@mysql/models/'+model+'/data/javascript');
        throw e;
      }      
    }
  },
  
  loadCall: function(query,parameters,data)
  { var controller = query.split('.')[0];
    var action = query.split('.')[1];
    this.loadController(controller);
    // return action
    if (this[controller][action] === undefined) throw new Error(query+' action not found');
    return this[controller][action].bind(this[controller]);
  },
  
  file: function(queryString,data)
  { var parameters = queryString.parseQueryString();
    var name = parameters.name;
    var type = parameters.type;
    iframe = $$('iframe[name='+name+']')[0];
    var span = new Element('span',{'class':'file'});
    span.grab(new Element('input',{name:name+'.name','class':'name',type:'text',value:data['name']}));
    span.grab(new Element('input',{name:name+'.size','class':'size',type:'text',value:data['size']}));
    span.grab(new Element('input',{name:name+'.type','class':'type',type:'text',value:data['type']}));
    span.grab(new Element('input',{name:name+'.data','class':'data',type:'text',value:data['data']}));
    var button = new Element('button',{text:'Remove'});
    button.addEvent('click',function(e){ 
      e.stop();
      $(e.target).getParent("span").getNext("iframe").show().getNext('br').show();
      $(e.target).getParent("span").destroy();
    });
    span.grab(button);
    span.grab(new Element('br'));
    span.inject(iframe,'before');
    if (type=='single'){ iframe.hide().getNext('br').hide(); }
    iframe.src='lib/file.php?'+queryString;
  }
  
});
