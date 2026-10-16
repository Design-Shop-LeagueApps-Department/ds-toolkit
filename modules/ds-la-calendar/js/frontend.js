/* LeagueApps Calendar (ds-toolkit). One init per .dslc; safe to call twice. */
(function(){
var MONTHS=['January','February','March','April','May','June','July','August','September','October','November','December'];
var DOW=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function parse(d){var p=d.split('-');return new Date(+p[0],+p[1]-1,+p[2]);}
function ymd(dt){return dt.getFullYear()+'-'+String(dt.getMonth()+1).padStart(2,'0')+'-'+String(dt.getDate()).padStart(2,'0');}
function shift(ym,n){var p=ym.split('-');var d=new Date(+p[0],+p[1]-1+n,1);return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0');}
function label(ym){var p=ym.split('-');return MONTHS[+p[1]-1]+' '+p[0];}
function cls(e){return e.type==='Tournament'?'t-tour':(e.type==='Game'?'t-game':'t-other');}
var UI='fl-content-ui-button ';

function init(root){
  if(!root||root.dataset.ready){return;}
  var src=root.querySelector('.dslc-data'); if(!src){return;}
  root.dataset.ready=1;
  var data={}; try{data=JSON.parse(src.textContent)||{};}catch(e){}
  var events=data.events||[], cfg=data.config||{}, today=root.dataset.today;
  var body=root.querySelector('.dslc-body'), title=root.querySelector('.dslc-month');
  var sel=root.querySelector('.dslc-team'), dlg=root.querySelector('.dslc-dialog');
  var prev=root.querySelector('.dslc-prev'), next=root.querySelector('.dslc-next'), nav=root.querySelector('.dslc-nav');
  var phone=window.matchMedia('(max-width:767px)');
  var state={view:phone.matches?cfg.viewPhone:cfg.viewDesktop,team:'',ym:null,day:null};

  // The month title is an h2, so it takes the site's heading font; reuse it for date numbers.
  if(title){root.style.setProperty('--dslc-head',getComputedStyle(title).fontFamily);}

  if(sel){
    var teams=data.teams||[];
    if(cfg.groupByAge){
      var groups={};
      teams.forEach(function(t){var m=/^(\d+)/.exec(t);var k=m?m[1]+'s':'Other';(groups[k]=groups[k]||[]).push(t);});
      Object.keys(groups).sort(function(a,b){return (parseInt(a)||999)-(parseInt(b)||999);}).forEach(function(k){
        var og=document.createElement('optgroup');og.label=k;
        groups[k].forEach(function(t){var o=document.createElement('option');o.value=t;o.textContent=t;og.appendChild(o);});
        sel.appendChild(og);
      });
    }else{
      teams.forEach(function(t){var o=document.createElement('option');o.value=t;o.textContent=t;sel.appendChild(o);});
    }
  }

  function visible(){
    if(!state.team){return events;}
    return events.filter(function(e){return e.teams.some(function(t){return t.n===state.team;});});
  }
  function teamTime(e){var t=e.teams.filter(function(x){return x.n===state.team;})[0];return t&&t.t?t.t:'';}
  function teamText(e){
    if(state.team){var tt=teamTime(e);return state.team+(tt?' · '+tt:'');}
    var n=e.teams.length;return n?(n===1?e.teams[0].n:n+' teams'):(e.time||'');
  }
  function whenText(e){return (state.team&&teamTime(e))||e.time||(e.times?'Times vary by team':'All day');}
  function byDay(list){var m={};list.forEach(function(e){(m[e.d]=m[e.d]||[]).push(e);});return m;}

  var upcoming=events.filter(function(e){return e.d>=today;});
  var first=(upcoming[0]||events[0]||{d:today}).d.slice(0,7), last=(events.length?events[events.length-1].d:today).slice(0,7);
  if(first>last){last=first;}
  state.ym=first;

  function chip(e){
    return '<button type="button" class="'+UI+'dslc-chip '+cls(e)+'" data-i="'+events.indexOf(e)+'"><b>'+esc(e.title)+'</b><span>'+esc(teamText(e))+'</span></button>';
  }
  function teamsHtml(teams){
    if(!cfg.showTeams||!teams.length){return '';}
    var ul='<ul class="dslc-teams">'+teams.map(function(t){return '<li>'+esc(t.n)+'</li>';}).join('')+'</ul>';
    return teams.length>cfg.collapseAfter?'<details class="dslc-tm"><summary>'+teams.length+' teams</summary>'+ul+'</details>':ul;
  }
  // Consecutive days of the same event at the same place read as one list entry ("Jan 16-17").
  function spans(list){
    var out=[];
    list.forEach(function(e){
      var p=out[out.length-1];
      if(p&&p.e.title===e.title&&p.e.loc===e.loc){
        var nd=parse(p.end);nd.setDate(nd.getDate()+1);
        if(ymd(nd)===e.d){
          p.end=e.d;
          e.teams.forEach(function(t){if(!p.teams.some(function(x){return x.n===t.n;})){p.teams.push(t);}});
          if(p.time!==(e.time||'')){p.time='';}
          return;
        }
      }
      out.push({e:e,end:e.d,teams:e.teams.slice(),time:e.time||''});
    });
    return out;
  }
  function rowHtml(s){
    var e=s.e,d=parse(e.d),end=parse(s.end),multi=s.end!==e.d;
    var when=state.team?whenText(e):(multi?(s.time||'All days'):whenText(e));
    var num=multi?(d.getMonth()===end.getMonth()?d.getDate()+'–'+end.getDate():d.getDate()+'+'):d.getDate();
    var dow=multi?DOW[d.getDay()]+'–'+DOW[end.getDay()]:DOW[d.getDay()];
    return '<div class="dslc-row"><div class="dslc-date'+(multi?' is-multi':'')+'"><small>'+dow+'</small><strong>'+num+'</strong><small>'+MONTHS[d.getMonth()].slice(0,3)+'</small></div><div>'+
      '<h3><button type="button" class="'+UI+'" data-i="'+events.indexOf(e)+'">'+esc(e.title)+'</button></h3>'+
      '<p class="dslc-meta"><span class="dslc-tag '+cls(e)+'">'+esc(e.label||e.type)+'</span>'+esc(when)+(e.loc?' · '+esc(e.loc):'')+'</p>'+
      (state.team?'':teamsHtml(s.teams))+'</div></div>';
  }
  function listHtml(list,months){
    var h='',cur='';
    spans(list).forEach(function(s){
      var m=s.e.d.slice(0,7);
      if(months&&m!==cur){cur=m;h+='<h3 class="dslc-list-month">'+esc(label(m))+'</h3>';}
      h+=rowHtml(s);
    });
    return h;
  }
  function empty(){return '<p class="dslc-empty">'+esc(cfg.emptyText)+'</p>';}

  function renderMonth(){
    if(nav){nav.style.display='';}
    title.textContent=label(state.ym);
    prev.disabled=state.ym<=first; next.disabled=state.ym>=last;
    var p=state.ym.split('-'), start=new Date(+p[0],+p[1]-1,1), month=start.getMonth();
    var cursor=new Date(start);cursor.setDate(1-start.getDay());
    var map=byDay(visible()), h='<div class="dslc-grid" role="grid">', max=cfg.maxChips||3;
    DOW.forEach(function(d){h+='<div class="dslc-dow" role="columnheader">'+d+'</div>';});
    for(var i=0;i<42;i++){
      var key=ymd(cursor), list=map[key]||[], out=cursor.getMonth()!==month;
      if(i===35&&out){break;}
      h+='<div class="dslc-cell'+(out?' is-out':'')+(key===today?' is-today':'')+(key===state.day?' is-sel':'')+'" data-day="'+key+'" role="gridcell" aria-label="'+esc(cursor.toDateString())+(list.length?', '+list.length+' event'+(list.length>1?'s':''):'')+'">';
      h+='<span class="dslc-num">'+cursor.getDate()+'</span>';
      list.slice(0,max).forEach(function(e){h+=chip(e);});
      if(list.length>max){h+='<button type="button" class="'+UI+'dslc-more" data-day="'+key+'">+'+(list.length-max)+' more</button>';}
      if(list.length){h+='<span class="dslc-dots" aria-hidden="true">'+list.slice(0,4).map(function(e){return '<i class="'+cls(e)+'"></i>';}).join('')+'</span>';}
      h+='</div>';
      cursor.setDate(cursor.getDate()+1);
    }
    h+='</div>';
    if(phone.matches){
      var dayList=state.day?(map[state.day]||[]):[];
      h+='<div class="dslc-day">'+(state.day?(dayList.length?listHtml(dayList,false):'<p class="dslc-empty">Nothing on this day.</p>'):'<p class="dslc-foot">Tap a day to see its events.</p>')+'</div>';
    }
    if(!events.length){h+=empty();}
    body.innerHTML=h;
  }
  function renderList(){
    if(nav){nav.style.display='none';}
    var list=visible().filter(function(e){return e.d>=today;});
    body.innerHTML=list.length?listHtml(list,true):empty();
  }
  function render(){
    root.querySelectorAll('.dslc-view').forEach(function(b){b.setAttribute('aria-pressed',b.dataset.view===state.view?'true':'false');});
    if(state.view==='month'){renderMonth();}else{renderList();}
  }

  function open(e){
    var d=parse(e.d), map=(e.lat&&e.lng)?'https://www.google.com/maps/search/?api=1&query='+e.lat+','+e.lng:(e.loc?'https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(e.loc):'');
    var bodyEl=root.querySelector('.dslc-dialog-body'), tid=bodyEl.dataset.titleId;
    var teams=cfg.showTeams?e.teams.map(function(t){return '<li>'+esc(t.n)+(e.times&&t.t?' <span>· '+esc(t.t)+'</span>':'')+'</li>';}).join(''):'';
    bodyEl.innerHTML=
      '<span class="dslc-tag '+cls(e)+'">'+esc(e.label||e.type)+'</span>'+
      '<h3 id="'+esc(tid)+'">'+esc(e.title)+'</h3>'+
      '<dl class="dslc-dl"><dt>Date</dt><dd>'+DOW[d.getDay()]+', '+MONTHS[d.getMonth()]+' '+d.getDate()+', '+d.getFullYear()+'</dd>'+
      '<dt>Time</dt><dd>'+esc(e.time||(e.times?'Times vary by team':'All day'))+'</dd>'+
      (e.loc?'<dt>Where</dt><dd>'+esc(e.loc)+'</dd>':'')+
      (e.note?'<dt>Notes</dt><dd>'+esc(e.note)+'</dd>':'')+'</dl>'+
      (teams?'<p class="dslc-meta" style="margin-top:16px;font-weight:600;color:var(--c-text)">'+e.teams.length+' team'+(e.teams.length>1?'s':'')+'</p><ul class="dslc-teams">'+teams+'</ul>':'')+
      '<div class="dslc-links">'+(e.url&&cfg.websiteText?'<a class="dslc-btn" href="'+esc(e.url)+'" target="_blank" rel="noopener">'+esc(cfg.websiteText)+'</a>':'')+
      (map&&cfg.directionsText?'<a class="dslc-btn alt" href="'+esc(map)+'" target="_blank" rel="noopener">'+esc(cfg.directionsText)+'</a>':'')+'</div>';
    if(dlg.showModal){dlg.showModal();}else{dlg.setAttribute('open','');}
  }

  root.addEventListener('click',function(ev){
    var t=ev.target.closest('[data-i]');
    if(t&&root.contains(t)&&!(phone.matches&&t.classList.contains('dslc-chip'))){open(events[+t.dataset.i]);return;}
    if(ev.target.closest('.dslc-more')){state.view='list';render();return;}
    var cell=ev.target.closest('.dslc-cell');
    if(cell&&phone.matches){state.day=cell.dataset.day;renderMonth();return;}
    var v=ev.target.closest('.dslc-view');
    if(v){state.view=v.dataset.view;render();}
  });
  prev.addEventListener('click',function(){if(state.ym>first){state.ym=shift(state.ym,-1);state.day=null;renderMonth();}});
  next.addEventListener('click',function(){if(state.ym<last){state.ym=shift(state.ym,1);state.day=null;renderMonth();}});
  if(sel){sel.addEventListener('change',function(){state.team=sel.value;render();});}
  function shut(){if(dlg.close){dlg.close();}else{dlg.removeAttribute('open');}}
  root.querySelector('.dslc-close').addEventListener('click',shut);
  dlg.addEventListener('click',function(ev){if(ev.target===dlg){shut();}});
  if(!dlg.showModal){document.addEventListener('keydown',function(ev){if('Escape'===ev.key&&dlg.hasAttribute('open')){shut();}});}
  if(phone.addEventListener){phone.addEventListener('change',function(){state.view=phone.matches?cfg.viewPhone:cfg.viewDesktop;render();});}
  render();
}

window.DSLACalendar={init:init};
function all(){document.querySelectorAll('.dslc').forEach(init);}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',all);}else{all();}
})();
