/* LeagueApps Team Schedule (ds-toolkit): type filter, "Show more", heading font hand-off. Safe to call twice. */
(function(){
function init(root){
  if(!root||root.dataset.ready){return;}
  root.dataset.ready=1;
  // Dates and month headings take the site's heading font (read from the nearest heading on the page).
  var h=root.closest('.fl-builder-content')||document;h=h.querySelector('h2,h3');
  if(h){var cs=getComputedStyle(h);root.style.setProperty('--s-head',cs.fontFamily);root.style.setProperty('--s-head-w',cs.fontWeight);}
  var rows=[].slice.call(root.querySelectorAll('.dss-row'));
  if(!rows.length){return;}
  var step=parseInt(root.getAttribute('data-rows'),10)||8;
  var limit=step;
  var f=root.getAttribute('data-f')||'';
  var more=root.querySelector('.dss-more');
  function paint(){
    var n=0;
    rows.forEach(function(r){
      var match=!f||r.getAttribute('data-g')===f;
      if(match){n++;}
      r.hidden=!match||(more?n>limit:false);
    });
    root.querySelectorAll('.dss-table tbody').forEach(function(b){
      b.hidden=!b.querySelector('.dss-row:not([hidden])');
    });
    if(more){more.hidden=n<=limit;}
  }
  var bar=root.querySelector('.dss-filters');
  if(bar){
    bar.addEventListener('click',function(ev){
      var b=ev.target.closest('.dss-chip'); if(!b){return;}
      f=b.getAttribute('data-f')||'';
      limit=step;
      bar.querySelectorAll('.dss-chip').forEach(function(x){x.setAttribute('aria-pressed',x===b?'true':'false');});
      paint();
    });
  }
  if(more){
    more.addEventListener('click',function(){
      var first=null,n=0;
      rows.forEach(function(r){ if(!f||r.getAttribute('data-g')===f){ n++; if(n===limit+1){first=r;} } });
      limit+=step;
      paint();
      // Move focus to the first new row so keyboard and screen-reader users land on what appeared.
      var c=first&&first.querySelector('.dss-title');
      if(c){c.setAttribute('tabindex','-1');c.focus({preventScroll:true});}
    });
  }
}
window.DSLASchedule={init:init};
function all(){document.querySelectorAll('.dss').forEach(init);}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',all);}else{all();}
})();
