/* LeagueApps Roster (ds-toolkit): position filter, carousel arrows, heading font hand-off. Safe to call twice. */
(function(){
function init(root){
  if(!root||root.dataset.ready){return;}
  root.dataset.ready=1;
  // The team name is a heading and takes the site's heading font; the medallions and names reuse it.
  var h=root.querySelector('.dsr-name')||root.querySelector('.dsr-sub');
  if(h){var cs=getComputedStyle(h);root.style.setProperty('--r-head',cs.fontFamily);root.style.setProperty('--r-head-w',cs.fontWeight);}
  // Carousel: arrows step one view at a time and hide when everything fits.
  var track=root.querySelector('.dsr--carousel .dsr-grid');
  var prev=root.querySelector('.dsr-prev'), next=root.querySelector('.dsr-next');
  function nav(){
    if(!track||!prev||!next){return;}
    var over=track.scrollWidth>track.clientWidth+2;
    prev.hidden=next.hidden=!over;
    prev.disabled=track.scrollLeft<=2;
    next.disabled=track.scrollLeft+track.clientWidth>=track.scrollWidth-2;
  }
  if(track&&prev&&next){
    var step=function(dir){var gap=parseFloat(getComputedStyle(track).columnGap)||16;track.scrollBy({left:dir*(track.clientWidth+gap),behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});};
    prev.addEventListener('click',function(){step(-1);});
    next.addEventListener('click',function(){step(1);});
    track.addEventListener('scroll',nav,{passive:true});
    window.addEventListener('resize',nav);
    nav();
  }
  var bar=root.querySelector('.dsr-filters'); if(!bar){return;}
  var cards=root.querySelectorAll('.dsr-card');
  bar.addEventListener('click',function(ev){
    var b=ev.target.closest('.dsr-chip'); if(!b){return;}
    var pos=b.getAttribute('data-pos');
    bar.querySelectorAll('.dsr-chip').forEach(function(x){x.setAttribute('aria-pressed',x===b?'true':'false');});
    cards.forEach(function(c){c.hidden=!!pos&&c.getAttribute('data-pos')!==pos;});
    if(track){track.scrollLeft=0;nav();}
  });
}
window.DSLARoster={init:init};
function all(){document.querySelectorAll('.dsr').forEach(init);}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',all);}else{all();}
})();
