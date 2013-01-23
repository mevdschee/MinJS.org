function dump(arr,level)
{ var dumped_text = "";
  if(!level) level = 0;

  //The padding given at the beginning of the line.
  var level_padding = "";
  for(var j=0;j<level+1;j++) level_padding += "    ";

  var parentType = typeof(arr);
  if (arr && arr['$family'] && arr['$family']['name'])
  { parentType = arr['$family']['name'];
  }

  if(parentType == 'object' || parentType == 'array')
  { //Array
    for(var item in arr) 
    { if (item == '$family') continue;
      var value = arr[item];
      type = typeof(value)
      if (value && value['$family'] && value['$family']['name'])
      { type = value['$family']['name'];
      }

      if(type == 'object' && value===null)
      { dumped_text += level_padding + item + " => (null)\n";
      } 
      else if(type == 'object' || type == 'array')
      { //If it is an object,
        dumped_text += level_padding + "'" + item + "' ... ("+type+")\n";
        dumped_text += dump(value,level+1);
      }
      else if(type == 'function')
      { if (parentType != 'array') dumped_text += level_padding + "'" + item + "' => (function)\n";
      }
      else
      { dumped_text += level_padding + "'" + item + "' => \"" + value + "\"\n";
      }
    }
  }
  else
  { //Stings/Chars/Numbers etc.
    dumped_text = "===>"+arr+"<===("+typeof(arr)+")";
  }
  return dumped_text;
}

function debug(p)
{ alert(dump(p));
}

