minjs.inflector=new new Class(
{ Extends: minjs.Model,

  fields: function(table,relations)
  { var queries = [];
    queries.push([table,'records:COLUMN_NAME,COLUMN_DEFAULT,IS_NULLABLE,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH','inflector.fields',{table_name:table}]);
    if (relations)
    { for (var t in relations.hasOne)
      { var table = relations.hasOne[t];
        queries.push([table,'records:COLUMN_NAME,COLUMN_DEFAULT,IS_NULLABLE,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH','inflector.fields',{table_name:table}]);
      }
      for (var t in relations.hasMany)
      { var table = relations.hasMany[t];
        queries.push([table,'records:COLUMN_NAME,COLUMN_DEFAULT,IS_NULLABLE,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH','inflector.fields',{table_name:table}]);
      }
    }
    return this.queries(queries);
  },
  
  relations: function(table)
  { var queries = [];
    queries.push(['belongsTo','list:COLUMN_NAME,REFERENCED_TABLE_NAME,','inflector.relations',{table_name:table}]);
    queries.push(['hasMany','list:TABLE_NAME,COLUMN_NAME,','inflector.relations',{referenced_table_name:table}]);
    queries.push(['hasOne','list:TABLE_NAME,CONSTRAINT_NAME','inflector.constraints',{constraint_name:table.singularize()+'_id',constraint_type:'UNIQUE'}]);
    var relations = this.queries(queries);
    var hasOne = {};
    var hasMany = {};
    var hasAndBelongsToMany = {};
    for (var t in relations.hasMany)
    { if (relations.hasOne[t] == relations.hasMany[t])
      { hasOne[relations.hasOne[t]] = t;
      }
      else
      { var tables = t.split('_');
        if (tables.length==2 && (tables[0]==table || tables[1]==table))
        { var o = tables[tables[0]==table?1:0];
          hasAndBelongsToMany[t] = o;
        }
        else
        { hasMany[relations.hasMany[t]] = t;
        }
      }
    }
    relations.hasOne = hasOne;
    relations.hasMany = hasMany;
    relations.hasAndBelongsToMany = hasAndBelongsToMany;
    return relations;
  }
  
});
