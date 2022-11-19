<?php
if(isset($_GET['keyword'])) {
	$keywords_string = $_GET['keyword'];
}
 /* require('functions.php');
		$keywords_string = ""; 
		if(isset($_POST['engine'])){

		$arr = explode("\r\n", trim($_POST['keywords']));
//		$csv_name = $arr[0]."-".$_POST['engine'].".xls";

		for ($i = 0; $i < count($arr); $i++) {
		   		$line = $arr[$i];
			   if($i == count($arr)){
			   		$keywords_string .= $line;
			   } else {
			     	$keywords_string .= $line.",";	
			   }
			}
		} 
 */
?>
<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Keyword Suggest - Keyword Decode</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />

    <link rel="stylesheet" href="assets/dist/css/tailwind.output.css" />
    <script src="assets/dist/js/jquery-3.5.1.min.js"></script> 
    <script
      src="assets/dist/js/alpine.min.js"
      defer
    ></script>
    <script src="assets/dist/js/init-alpine.js"></script>
    <script type="text/javascript">
         
        var hashMapResults = {}; 
        var numOfKeywords = 0; 
        var doWork = false; 
        var keywordsToQuery = new Array(); 
        var keywordsToQueryIndex = 0; 
        var queryflag = false; 
        
        window.setInterval(DoJob, 750); 
        
        function StartJob()
        {
            if(doWork == false)
            {                
                hashMapResults = {}; 
                numOfKeywords = 0; 
                keywordsToQuery = new Array();
                keywordsToQueryIndex = 0; 
                
                hashMapResults[""] = 1; 
                hashMapResults[" "] = 1; 
                hashMapResults["  "] = 1; 
                
                var ks = $('#input').val().split("\n");
                var i = 0; 
                for(i = 0; i < ks.length; i++)
                {
                    keywordsToQuery[keywordsToQuery.length] = ks[i];

                    var j = 0; 
                    for(j = 0; j < 26; j++)
                    {
                        var chr = String.fromCharCode(97 + j);
                        var currentx = ks[i] + ' ' + chr; 
                        keywordsToQuery[keywordsToQuery.length] = currentx; 
                        hashMapResults[currentx] = 1;
                    }
                }
                //document.getElementById("input").value = ''; 
                document.getElementById("input").value += "\r\n";
                
                doWork = true; 
                $('#startjob').val('Stop Searching');
            }
            else
            {
                doWork = false; 
                alert("Keyword Search is Now Stopped"); 
                $('#startjob').val('Start Searching');
            }
        }
        
        function DoJob()
        {
            if(doWork == true && queryflag == false)
            {
                if(keywordsToQueryIndex < keywordsToQuery.length)
                {
                    var currentKw = keywordsToQuery[keywordsToQueryIndex]; 
                    QueryKeyword(currentKw);
                    keywordsToQueryIndex++;
					$('#currentQuery').html(currentKw);
                    
                }
                else 
                {
                    if (numOfKeywords != 0)
                    {
                        alert("Done"); 
                        doWork = false; 
                        $('#startjob').val('Start Searching');
                    }
                    else
                    {
                        keywordsToQueryIndex = 0; 
                    }
                }
            }
        }
        
        function QueryKeyword(keyword)
        {
            var querykeyword = keyword;
            //var querykeyword = encodeURIComponent(keyword); 
            var queryresult = ''; 
            queryflag = true; 
            var engine = $('#engine').val();


			if(engine == "google"){
            $.ajax({
                url: "https://suggestqueries.google.com/complete/search",
                jsonp: "jsonp",
                dataType: "jsonp",
                data: {
                q: querykeyword,
                client: "chrome"
                },
                success: function(res) {
                    var retList = res[1];
                    
                    var i = 0; 
                    var sb = ''; 
                    for(i = 0; i < retList.length; i++)
                    {
                        var currents = CleanVal(retList[i]); 
                        if(hashMapResults[currents] != 1)
                        {
                            hashMapResults[currents] = 1; 
                            sb = sb + CleanVal(retList[i]) + '\r\n';
                            numOfKeywords++; 
                            
                            keywordsToQuery[keywordsToQuery.length] = currents; 
                            
                            var j = 0; 
                            for(j = 0; j < 26; j++)
                            {
                                var chr = String.fromCharCode(97 + j);
                                var currentx = currents + ' ' + chr; 
                                keywordsToQuery[keywordsToQuery.length] = currentx; 
                                hashMapResults[currentx] = 1;
                            }
                        }
                    }
                    $("#numofkeywords").html(numOfKeywords);
                    $("#progress").width(numOfKeywords+"%"); 
                    document.getElementById("input").value += sb;
                    var textarea = document.getElementById("input");
                    textarea.scrollTop = textarea.scrollHeight;
                    queryflag = false; 
                }
            }); 

			} 



			if(engine == "bing"){
	            $.ajax({
	                url: "https://api.bing.com/osjson.aspx?JsonType=callback&JsonCallback=?",
	                jsonp: "jsonp",
	                dataType: "jsonp",
	                data: {
	                Query: querykeyword,
	                Market: "en-us"
	                },
	                success: function(res) {
	                    var retList = res[1];
	                    
	                    var i = 0; 
	                    var sb = ''; 
	                    for(i = 0; i < retList.length; i++)
	                    {
	                        var currents = CleanVal(retList[i]); 
	                        if(hashMapResults[currents] != 1)
	                        {
	                            hashMapResults[currents] = 1; 
	                            sb = sb + CleanVal(retList[i]) + '\r\n';
	                            numOfKeywords++; 
	                            
	                            keywordsToQuery[keywordsToQuery.length] = currents; 
	                            
	                            var j = 0; 
	                            for(j = 0; j < 26; j++)
	                            {
	                                var chr = String.fromCharCode(97 + j);
	                                var currentx = currents + ' ' + chr; 
	                                keywordsToQuery[keywordsToQuery.length] = currentx; 
	                                hashMapResults[currentx] = 1;
	                            }
	                        }
	                    }
	                    $("#numofkeywords").html(numOfKeywords); 
	                    document.getElementById("input").value += sb;
	                    var textarea = document.getElementById("input");
	                    textarea.scrollTop = textarea.scrollHeight;
	                    queryflag = false; 
	                }
	            }); 

				}



			if(engine == "youtube"){
	            $.ajax({
                    url: "https://suggestqueries.google.com/complete/search",
                    jsonp: "jsonp",
                    dataType: "jsonp",
                    data: {
                    q: querykeyword,
                    client: "chrome",
                    ds: "yt"
                    },
	                success: function(res) {
	                    var retList = res[1];
	                    
	                    var i = 0; 
	                    var sb = ''; 
	                    for(i = 0; i < retList.length; i++)
	                    {
	                        var currents = CleanVal(retList[i]); 
	                        if(hashMapResults[currents] != 1)
	                        {
	                            hashMapResults[currents] = 1; 
	                            sb = sb + CleanVal(retList[i]) + '\r\n';
	                            numOfKeywords++; 
	                            
	                            keywordsToQuery[keywordsToQuery.length] = currents; 
	                            
	                            var j = 0; 
	                            for(j = 0; j < 26; j++)
	                            {
	                                var chr = String.fromCharCode(97 + j);
	                                var currentx = currents + ' ' + chr; 
	                                keywordsToQuery[keywordsToQuery.length] = currentx; 
	                                hashMapResults[currentx] = 1;
	                            }
	                        }
	                    }
	                    $("#numofkeywords").html(numOfKeywords); 
	                    document.getElementById("input").value += sb;
	                    var textarea = document.getElementById("input");
	                    textarea.scrollTop = textarea.scrollHeight;
	                    queryflag = false; 
	                }
	            }); 

				}			





			if(engine == "yahoo"){
	            $.ajax({
                    url: "https://search.yahoo.com/sugg/gossip/gossip-us-ura/",
                    dataType: "jsonp",
                    data: {
                    command: querykeyword,
                    nresults: "20",
                    output: "jsonp"
                    },
	                success: function(res) {
                        var retList = [];
                        $.each(res.gossip.results, function(i, val) {
                        	retList.push(val.key);
                        });
	                    
	                    var i = 0; 
	                    var sb = ''; 
	                    for(i = 0; i < retList.length; i++)
	                    {
	                        var currents = CleanVal(retList[i]); 
	                        if(hashMapResults[currents] != 1)
	                        {
	                            hashMapResults[currents] = 1; 
	                            sb = sb + CleanVal(retList[i]) + '\r\n';
	                            numOfKeywords++; 
	                            
	                            keywordsToQuery[keywordsToQuery.length] = currents; 
	                            
	                            var j = 0; 
	                            for(j = 0; j < 26; j++)
	                            {
	                                var chr = String.fromCharCode(97 + j);
	                                var currentx = currents + ' ' + chr; 
	                                keywordsToQuery[keywordsToQuery.length] = currentx; 
	                                hashMapResults[currentx] = 1;
	                            }
	                        }
	                    }
	                    $("#numofkeywords").html(numOfKeywords);
	                    document.getElementById("input").value += sb;
	                    var textarea = document.getElementById("input");
	                    textarea.scrollTop = textarea.scrollHeight;
	                    queryflag = false; 
	                }
	            }); 

				}



			if(engine == "amazon"){
	            $.ajax({
	                url: "https://completion.amazon.com/search/complete",
	                dataType: "jsonp",
	                data: {
	                q: querykeyword,
	                method: "completion",
	                'search-alias': "aps",
	                mkt: "1"
	                },
	                success: function(res) {
	                    var retList = res[1];
	                    
	                    var i = 0; 
	                    var sb = ''; 
	                    for(i = 0; i < retList.length; i++)
	                    {
	                        var currents = CleanVal(retList[i]); 
	                        if(hashMapResults[currents] != 1)
	                        {
	                            hashMapResults[currents] = 1; 
	                            sb = sb + CleanVal(retList[i]) + '\r\n';
	                            numOfKeywords++; 
	                            
	                            keywordsToQuery[keywordsToQuery.length] = currents; 
	                            
	                            var j = 0; 
	                            for(j = 0; j < 26; j++)
	                            {
	                                var chr = String.fromCharCode(97 + j);
	                                var currentx = currents + ' ' + chr; 
	                                keywordsToQuery[keywordsToQuery.length] = currentx; 
	                                hashMapResults[currentx] = 1;
	                            }
	                        }
	                    }
	                    $("#numofkeywords").html(numOfKeywords); 
	                    document.getElementById("input").value += sb;
	                    var textarea = document.getElementById("input");
	                    textarea.scrollTop = textarea.scrollHeight;
	                    queryflag = false; 
	                }
	            }); 

				}



		if(engine == "ebay"){
	            $.ajax({
	                url: "ebay-ajax.php", 
					dataType:'json',
	                data: {
	                q: querykeyword,
	                v: "jsonp",
	                _dg: "1",
	                sId: "0"
	                },
	                success: function(res1) {
						 
						if(res1.res){
	                	var retList = res1.res.sug; 
	                    var i = 0; 
	                    var sb = '';
						
	                    for(i = 0; i < retList.length; i++)
	                    {
	                        var currents = CleanVal(retList[i]); 
	                        if(hashMapResults[currents] != 1)
	                        {
	                            hashMapResults[currents] = 1; 
	                            sb = sb + CleanVal(retList[i]) + '\r\n';
	                            numOfKeywords++; 
	                            
	                            keywordsToQuery[keywordsToQuery.length] = currents; 
	                            
	                            var j = 0; 
	                            for(j = 0; j < 26; j++)
	                            {
	                                var chr = String.fromCharCode(97 + j);
	                                var currentx = currents + ' ' + chr; 
	                                keywordsToQuery[keywordsToQuery.length] = currentx; 
	                                hashMapResults[currentx] = 1;
	                            }
	                        }
	                    }
	                    $("#numofkeywords").html(numOfKeywords); 
	                    document.getElementById("input").value += sb;
						 queryflag = false; 
	                    var textarea = document.getElementById("input");
	                    textarea.scrollTop = textarea.scrollHeight;
						if(res1.res.categories) {
						var catList = res1.res.categories;
						var ct = '';
							 for(i = 0; i < catList.length; i++)
							{
								ct = ct + catList[i] +'\r\n';
							}
							document.getElementById("input").value += ct;					
						}		
							
	                	} else {
						alert("No Result Found.");
						StartJob();
						}
	                   
	                
	            }
	            }); 

				}			


			         
        }
        
        function CleanVal(input)
        {       
            var val = input;
            val = val.replace("\\u003cb\\u003e", "");
            val = val.replace("\\u003c\\/b\\u003e", "");
            val = val.replace("\\u003c\\/b\\u003e", "");
            val = val.replace("\\u003cb\\u003e", "");
            val = val.replace("\\u003c\\/b\\u003e", "");
            val = val.replace("\\u003cb\\u003e", "");
            val = val.replace("\\u003cb\\u003e", "");
            val = val.replace("\\u003c\\/b\\u003e", "");
            val = val.replace("\\u0026amp;", "&");
            val = val.replace("\\u003cb\\u003e", "");
            val = val.replace("\\u0026", "");
            val = val.replace("\\u0026#39;", "'");
            val = val.replace("#39;", "'");
            val = val.replace("\\u003c\\/b\\u003e", "");
            val = val.replace("\\u2013", "2013");
            if (val.length > 4 && val.substring(0, 4) == "http") val = "";
            return val; 
        }
        
 
      	
        function FormSubmit(e) {
			event.preventDefault();
            doWork = false; 
            $('#startjob').val('Start Searching');
            var refval = $('#input').val();
			$('#input').val(refval.replace(new RegExp("[\r\n]", "gm"), ","));
        	//$("form").submit();
			return false;			
        }    
    </script>   
  </head>
  <body>
    <div
      class="flex h-screen bg-gray-50 dark:bg-gray-900"
      :class="{ 'overflow-hidden': isSideMenuOpen }"
    >

      <div class="flex flex-col flex-1 w-full">

        <main class="h-full overflow-y-auto">
          <div class="container px-6 mx-auto grid">
            <h2
              class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200"
            >
              SEO Keyword Idea Generator / Suggestion Tool
            </h2>
            <div
              class="px-4 py-3 mb-8 bg-white rounded-lg shadow-md dark:bg-gray-800"
            >
<form name="searchbox" id="searchbox" action="keyword_suggest.php" onsubmit="return FormSubmit(event);"  method="POST">
            <div><strong>Please enter keywords and press "Start Searching" button!</strong></div>
            Keywords Found: &nbsp;<span id='numofkeywords'></span><br />
			Current Query: &nbsp; <div id="currentQuery"></div>
            <div class="w-full bg-gray-200 h-1">
  <div id="progress" class="bg-blue-600 h-1" style="width:0%;height:10px;"></div>
</div>
			<strong>Select Search Engine:</strong> <select class="block w-full mt-1 text-sm dark:text-gray-300 dark:border-gray-600 dark:bg-gray-700 form-select focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:focus:shadow-outline-gray" name="engine" id="engine">
			<optgroup label="Search">
			<option value="google">Google</option>
			<option value="yahoo">Yahoo</option>
			<option value="bing">Bing</option>
			<option value="youtube">YouTube</option>
			</optgroup>
			<optgroup label="Shopping">
			<option value="amazon">Amazon</option>
			<option value="ebay">Ebay</option>
			</optgroup>			
			</select><br /><br />            
            <textarea  name="keywords" id='input' class="block w-full mt-1 text-sm dark:text-gray-300 dark:border-gray-600 dark:bg-gray-700 form-textarea focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:focus:shadow-outline-gray" rows="10"><?php echo $keywords_string; ?></textarea><br><br>
            <div class="flex items-center justify-center space-x-4">
            <input id=startjob onclick="StartJob();" type=button class="px-5 py-3 font-medium leading-5 text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple" value="Stat Searching">
            <input class="px-5 py-3 font-medium leading-5 text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple" type="submit" onclick="return FormSubmit(event);" value="Export Keywords">
            </div>
            <div id=container>

            </div>
            <textarea id=queryoutput style="display:none;">hello</textarea><br>
</form> 
			</div>           
            <p
              class="my-6 text-xs font-semibold text-gray-600 dark:text-gray-400"
            >
             Copyright &copy; 2022 <a href="https://www.sutlej.net" target="_blank">Sutlej Solutions</a> <br /> Version: 1.0.2
            </p>
          </div>
        </main>
      </div>
    </div>
  </body>
 
</html>
