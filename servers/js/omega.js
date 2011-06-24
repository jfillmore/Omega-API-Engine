/* omega - node.js server
   http://code.google.com/p/theomega/
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

var http=require("http");var om=require("./omlib");
var Omega={Request:function(req){var req;req={api:null};req._sock=req;req._answer=function(){return"foo!"};return req},Response:function(res){var resp;resp={_sock:res,data:null,result:false,encoding:"json",headers:{"Content-Type":"text/plain","Cache-Control":"no-cache"},return_code:"200"};resp.set_result=function(result){resp.result=result?true:false};resp.encode=function(){var answer={result:resp.result};if(resp.result)answer.data=resp.data;else{answer.reason=resp.data;answer.data=null}return om.json.encode(answer)};
return resp},Server:function(conf){var omega;omega={_session_id:null,_session:null,_conf:conf,config:null,request:null,response:null,service:null,service_name:""};omega.answer=function(req,res){var answer;omega.request=Omega.Request(req);omega.response=Omega.Response(res);try{omega.response.data=omega.request._answer();omega.response.result=true}catch(e){omega.response.data=e;omega.response.return_code="500"}res.writeHead(omega.response.return_code,omega.response.headers);res.end(omega.response.encode())};
omega._server=http.createServer(omega.answer);omega._server.listen(conf.port,conf.iface);if(omega._conf.verbose)console.log(om.sprintf("Server running at http://%s:%d/",conf.iface,conf.port));return omega}};exports.Server=Omega.Server;
