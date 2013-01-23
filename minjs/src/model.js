if (typeof minjs === 'undefined') minjs = {};
minjs.Model = new Class(
{ 
  errors: [],
  
  query: function(type,query,parameters)
  { var results = this.queries([['data',type,query,parameters]]);
    if (results) return results.data;
    else return results;
  },
  
  queries: function(queryQueue)
  { if (!queryQueue || queryQueue.length==0) return true;
    this.errors = [];
    var data = [];
    data = Hash.toQueryString({queries:JSON.encode(queryQueue),csrf_key:minjs.csrfKey});
    var results = false;
    var request = new Request({'url':'minjs.php','data':data,async:false,'onSuccess':function(data){
      csrfKey = request.getHeader('X-MinJS-CSRF-Key');
      if (csrfKey) minjs.csrfKey = csrfKey;
      if (typeof data=='string' && data.substring(0,1)!='{')
      { if (data.match(new RegExp('validation errors')))
        { this.errors = data.split(': ')[1].split(',');
        }
        else this.errors = [data];
      }
      else 
      { results = JSON.decode(data);
      }
    }.bind(this)});
    request.send();
    if (this.errors.length) throw this.errors;
    return results;
  }
  
});
