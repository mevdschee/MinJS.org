
/*
 * Strings
 * 
 * */

var Strings = new Class({

  Implements : [Events,Options],
  BindAll: true,
  
  _timeoutInSeconds: false,
  _ajax: false,
  _strings: {},
 
  initialize: function(timeoutInSeconds)
  { this._timeoutInSeconds = timeoutInSeconds;
    window['__'] = this.get;
  },
  
  load: function(stringsUrl)
  { this._ajax = new Ajax(this._timeoutInSeconds);
    this._ajax.addEvent('load',this.onLoad);
    this._ajax.addEvent('fail',this.fireEvent.pass('failure'));
    this._ajax.addEvent('timeout',this.fireEvent.pass('timeout'));
    this._ajax.load(stringsUrl);
  },

  onLoad: function(strings)
  { this._strings = strings;
    this.fireEvent('load');
  },

  get: function(id,type)
  { if (!this._strings[id]) return id;
    if (type) return this._strings[id][type]
    else return this._strings[id];
  }
  
});
