/* LeagueApps Roster (ds-toolkit): position filter + heading font hand-off. Safe to call twice. */
(function(){
function init(root){
  if(!root||root.dataset.ready){return;}
  root.dataset.ready=1;
  // The team name is a heading and takes the site's heading font; the medallions and names reuse it.
  var h=root.querySelector('.dsr-name')||root.querySelector('.dsr-sub');
  if(h){var cs=getComputedStyle(h);root.style.setProperty('--r-head',cs.fontFamily);root.style.setProperty('--r-head-w',cs.fontWeight);}
  var bar=root.querySelector('.dsr-filters'); if(!bar){return;}
  var cards=root.querySelectorAll('.dsr-card');
  bar.addEventListener('click',function(ev){
    var b=ev.target.closest('.dsr-chip'); if(!b){return;}
    var pos=b.getAttribute('data-pos');
    bar.querySelectorAll('.dsr-chip').forEach(function(x){x.setAttribute('aria-pressed',x===b?'true':'false');});
    cards.forEach(function(c){c.hidden=!!pos&&c.getAttribute('data-pos')!==pos;});
  });
}
window.DSLARoster={init:init};
function all(){document.querySelectorAll('.dsr').forEach(init);}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',all);}else{all();}
})();
