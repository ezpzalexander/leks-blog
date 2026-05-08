function toggleDark(){
  var dark=document.documentElement.getAttribute("data-theme")==="dark";
  if(dark){document.documentElement.removeAttribute("data-theme");}
  else{document.documentElement.setAttribute("data-theme","dark");}
  localStorage.setItem("theme",dark?"dark":"light");
  document.querySelectorAll(".dark-toggle").forEach(function(b){b.innerHTML=dark?"&#9728;":"&#9790;";});
}
document.addEventListener("DOMContentLoaded",function(){
  var dark=document.documentElement.getAttribute("data-theme")==="dark";
  document.querySelectorAll(".dark-toggle").forEach(function(b){b.innerHTML=dark?"&#9728;":"&#9790;";});
  var top=document.querySelector(".scroll-top");
  var bar=document.querySelector(".progress-bar");
  if(top)window.addEventListener("scroll",function(){top.classList.toggle("visible",window.scrollY>300);},{passive:true});
  if(bar){
    var prose=document.querySelector(".prose");
    if(prose)window.addEventListener("scroll",function(){
      var p=prose.getBoundingClientRect(),h=prose.offsetHeight,w=window.innerHeight,scrolled=-p.top/(h-w);
      bar.style.width=Math.min(100,Math.max(0,scrolled*100))+"%";
    },{passive:true});
  }
  if("IntersectionObserver" in window){
    var lazy=document.querySelectorAll("img[loading=lazy]");
    var obs=new IntersectionObserver(function(entries){entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add("loaded");obs.unobserve(e.target);}});});
    lazy.forEach(function(i){obs.observe(i);});
  }else{document.querySelectorAll("img[loading=lazy]").forEach(function(i){i.classList.add("loaded");});}
  document.addEventListener("keydown",function(e){
    if(e.target.tagName==="INPUT"||e.target.tagName==="TEXTAREA")return;
    if(e.key==="t")toggleDark();
    if(e.key==="h"||e.key==="w")window.location.href="index.php";
    if(e.key==="b"&&history.length>1)history.back();
    if(e.key==="g")window.scrollTo({top:0,behavior:"smooth"});
  });
});
