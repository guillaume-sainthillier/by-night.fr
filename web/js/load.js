window.addEventListener ? 
window.addEventListener("load",init_load,false) : 
window.attachEvent && window.attachEvent("onload",init_load);

function init_load()
{
    //Base -> Lib -> Plugin -> Final
    load_scripts(js_scripts, ["base","lib","plugin","final"]);    
}

function getScript(url, success)
{
	var script = document.createElement('script');
	script.src = url;
	script.async = true;
	
	var head = document.getElementsByTagName('head')[0];
        var done=false;
	script.onload=script.onreadystatechange = function()
	{
    	if ( !done && (!this.readyState || this.readyState == 'loaded' || this.readyState == 'complete') )
        {
        	done = true;
        	if (typeof success === "function")
        	{
        	   success.call(url);
    	    }
        	script.onload = script.onreadystatechange = null;
        	head.removeChild(script);
    	}
	};
	head.appendChild(script);
}

function load_scripts(scripts, ordre)
{
    if(! ordre.length)
    {
        return;
    }
    
    var current = ordre[0];    
    var queue = new Array(scripts[current].length);

    for(var i in scripts[current])
    {
        var script = scripts[current][i];        
        getScript(script, function()
        {
            queue.pop();
            if(! queue.length)
            {
                load_scripts(scripts, ordre.slice(1));
            }
        });
    }    
}