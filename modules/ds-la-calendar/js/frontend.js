/* LeagueApps Calendar (ds-toolkit). One init per .dslc; safe to call twice. */
(function(){
var MONTHS=['January','February','March','April','May','June','July','August','September','October','November','December'];
var DOW=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
var DAYS=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
var WD=['sun','mon','tue','wed','thu','fri','sat'];
var UI='fl-content-ui-button ';
function svg(w,body){return '<svg width="'+w+'" height="'+w+'" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false">'+body+'</svg>';}
var S='stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"';
var ICON={
  close:'<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
  chev:'<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="m6 3 5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  cal:svg(20,'<rect x="3" y="4" width="14" height="13" rx="2" '+S+'/><path d="M3 8h14M7 2.5v3M13 2.5v3" '+S+'/>'),
  pin:svg(20,'<path d="M10 18s6-5.2 6-10a6 6 0 1 0-12 0c0 4.8 6 10 6 10Z" '+S+'/><circle cx="10" cy="8" r="2.2" '+S+'/>'),
  users:svg(20,'<circle cx="7.5" cy="7" r="2.8" '+S+'/><path d="M2.5 16.5c.5-2.8 2.4-4.3 5-4.3s4.5 1.5 5 4.3M13 4.4a2.8 2.8 0 0 1 0 5.2M14.6 12.4c1.5.5 2.5 1.9 2.9 4.1" '+S+'/>'),
  info:svg(20,'<circle cx="10" cy="10" r="7.5" '+S+'/><path d="M10 9.2v4.6M10 6.4v.1" '+S+'/>'),
  add:svg(18,'<rect x="3" y="4" width="14" height="13" rx="2" '+S+'/><path d="M3 8h14M7 2.5v3M13 2.5v3M10 10.3v4.4M7.8 12.5h4.4" '+S+'/>'),
  google:svg(18,'<circle cx="10" cy="10" r="7" '+S+'/><path d="M13.4 8.6H10v2.6h2.1c-.3 1.1-1.1 1.8-2.1 1.8a2.9 2.9 0 1 1 1.9-5.1" '+S+'/>'),
  apple:svg(18,'<path d="M13.5 10.6c0-1.6 1.3-2.4 1.4-2.5-.8-1.1-2-1.3-2.4-1.3-1-.1-2 .6-2.5.6s-1.3-.6-2.2-.6c-1.1 0-2.2.7-2.8 1.7-1.2 2.1-.3 5.2.9 6.9.6.8 1.2 1.7 2.1 1.7.8 0 1.2-.5 2.2-.5s1.3.5 2.2.5c.9 0 1.5-.9 2-1.7.6-.9.9-1.8.9-1.9-.1 0-1.8-.7-1.8-2.9ZM11.9 5.6c.5-.6.8-1.4.7-2.1-.7 0-1.5.5-2 1.1-.4.5-.8 1.3-.7 2.1.8 0 1.5-.4 2-1.1Z" '+S+'/>'),
  outlook:svg(18,'<rect x="2.5" y="4.5" width="15" height="11" rx="1.6" '+S+'/><path d="m2.8 5.2 7.2 5.4 7.2-5.4" '+S+'/>'),
  file:svg(18,'<path d="M5 2.5h6.5L15 6v11.5H5z" '+S+'/><path d="M11.5 2.5V6H15M10 9v5.5M7.8 12.3 10 14.5l2.2-2.2" '+S+'/>'),
  link:svg(18,'<path d="M8.5 11.5a3.2 3.2 0 0 0 4.6 0l2.6-2.6a3.2 3.2 0 0 0-4.6-4.6l-.9.9M11.5 8.5a3.2 3.2 0 0 0-4.6 0L4.3 11.1a3.2 3.2 0 0 0 4.6 4.6l.9-.9" '+S+'/>')
};
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function pad(n){return (n<10?'0':'')+n;}
function parse(d){var p=d.split('-');return new Date(+p[0],+p[1]-1,+p[2]);}
function ymd(dt){return dt.getFullYear()+'-'+pad(dt.getMonth()+1)+'-'+pad(dt.getDate());}
function addDays(d,n){var x=parse(d);x.setDate(x.getDate()+n);return ymd(x);}
function shift(ym,n){var p=ym.split('-');var d=new Date(+p[0],+p[1]-1+n,1);return d.getFullYear()+'-'+pad(d.getMonth()+1);}
function label(ym){var p=ym.split('-');return MONTHS[+p[1]-1]+' '+p[0];}
function cls(e){return e.type==='Tournament'?'t-tour':((e.type==='Game'||e.type==='League')?'t-game':'t-other');}

/* ---- Times -> real moments, for Add to calendar (mirrors DS_Calendar_ICS::clock) ---- */
var fmts={};
function tzOffset(t,tz){
  try{
    var f=fmts[tz]||(fmts[tz]=new Intl.DateTimeFormat('en-US',{timeZone:tz,hourCycle:'h23',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit'}));
    var o={};f.formatToParts(new Date(t)).forEach(function(x){o[x.type]=x.value;});
    return Date.UTC(+o.year,+o.month-1,+o.day,+o.hour%24,+o.minute,+o.second)-t;
  }catch(err){return -new Date(t).getTimezoneOffset()*60000;}
}
function wall(day,h,mi,tz){ // a wall-clock time in tz -> Date
  var p=day.split('-'),guess=Date.UTC(+p[0],+p[1]-1,+p[2],h,mi),t=guess-tzOffset(guess,tz),t2=guess-tzOffset(t,tz);
  return new Date(t2);
}
var TRE=/^(?:([A-Za-z]{3})\s+)?(\d{1,2})(?::(\d{2}))?\s*([AP]M)(?:\s*[\u2013-]\s*(?:([A-Za-z]{3})\s+)?(\d{1,2})(?::(\d{2}))?\s*([AP]M))?$/i;
function h24(h,ap){return (+h%12)+(ap.toUpperCase()==='PM'?12:0);}
function clock(text,day,tz){
  var m=TRE.exec(String(text||'').trim()); if(!m){return null;}
  var sd=parse(day);
  if(m[1]){var w=WD.indexOf(m[1].toLowerCase());if(w>=0){sd.setDate(sd.getDate()-((sd.getDay()-w+7)%7));}}
  var s=wall(ymd(sd),h24(m[2],m[4]),+(m[3]||0),tz);
  if(!m[6]){return {s:s,e:new Date(s.getTime()+3600000),cross:false};}
  var ed=new Date(sd);
  if(m[5]){var w2=WD.indexOf(m[5].toLowerCase());if(w2>=0){ed.setDate(ed.getDate()+((w2-ed.getDay()+7)%7));}}
  var e=wall(ymd(ed),h24(m[6],m[8]),+(m[7]||0),tz);
  if(e<=s){ed.setDate(ed.getDate()+1);e=wall(ymd(ed),h24(m[6],m[8]),+(m[7]||0),tz);}
  return {s:s,e:e,cross:ymd(ed)!==ymd(sd)};
}
function zulu(d){return d.getUTCFullYear()+pad(d.getUTCMonth()+1)+pad(d.getUTCDate())+'T'+pad(d.getUTCHours())+pad(d.getUTCMinutes())+'00Z';}
function icsEsc(s){return String(s).replace(/\\/g,'\\\\').replace(/;/g,'\\;').replace(/,/g,'\\,').replace(/\r?\n/g,'\\n');}
function fold(line){ // 75 octets per line, never inside a character
  var out='',n=0;
  for(var i=0;i<line.length;i++){
    var c=line.charCodeAt(i),b=c<128?1:(c<2048?2:(c>=0xD800&&c<0xDC00?4:(c>=0xDC00&&c<0xE000?0:3)));
    if(b&&n+b>75){out+='\r\n ';n=1;}
    out+=line.charAt(i);n+=b;
  }
  return out;
}
function slug(s){return String(s).toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,60)||'event';}

function init(root){
  if(!root||root.dataset.ready){return;}
  var src=root.querySelector('.dslc-data'); if(!src){return;}
  root.dataset.ready=1;
  var data={}; try{data=JSON.parse(src.textContent)||{};}catch(err){}
  var events=data.events||[], cfg=data.config||{}, today=root.dataset.today, tz=cfg.tz||'America/New_York';
  var body=root.querySelector('.dslc-body'), title=root.querySelector('.dslc-month'), count=root.querySelector('.dslc-count');
  var sel=root.querySelector('.dslc-team'), dlg=root.querySelector('.dslc-dialog'), dbody=root.querySelector('.dslc-dialog-body');
  var prev=root.querySelector('.dslc-prev'), next=root.querySelector('.dslc-next'), nav=root.querySelector('.dslc-nav');
  var legend=root.querySelector('.dslc-legend');

  // Layout follows the calendar's own width, not the screen's.
  function measure(){
    var w=root.clientWidth||window.innerWidth;
    root.classList.toggle('is-narrow',w<700);
    root.classList.toggle('is-mid',w>=700&&w<1100);
    return w<700;
  }
  var narrow=measure();
  if(window.ResizeObserver){new ResizeObserver(function(){narrow=measure();}).observe(root);}
  else{window.addEventListener('resize',function(){narrow=measure();});}
  var state={view:narrow?cfg.viewPhone:cfg.viewDesktop,team:'',ym:null,day:null,open:null,ev:null,span:null};

  // The month title is an h2, so it takes the site's heading font and weight; every other heading reuses them.
  if(title){var cs=getComputedStyle(title);root.style.setProperty('--dslc-head',cs.fontFamily);root.style.setProperty('--dslc-head-w',cs.fontWeight);}

  if(sel){
    var teams=data.teams||[], progs=data.programs||[];
    var add=function(parent,text,value){var o=document.createElement('option');o.value=value;o.textContent=text;parent.appendChild(o);};
    if(cfg.groupByAge){
      var groups={};
      teams.forEach(function(t){var m=/^(\d+)/.exec(t);var k=m?m[1]+'s':'Other';(groups[k]=groups[k]||[]).push(t);});
      Object.keys(groups).sort(function(a,b){return (parseInt(a,10)||999)-(parseInt(b,10)||999);}).forEach(function(k){
        var og=document.createElement('optgroup');og.label=k;
        groups[k].forEach(function(t){add(og,t,'t:'+t);});
        sel.appendChild(og);
      });
    }else if(teams.length){
      var tg=progs.length?document.createElement('optgroup'):sel; if(progs.length){tg.label=cfg.teamsLabel||'Teams';sel.appendChild(tg);}
      teams.forEach(function(t){add(tg,t,'t:'+t);});
    }
    if(progs.length){
      var pg=teams.length?document.createElement('optgroup'):sel; // programs only: a plain list
      if(teams.length){pg.label=cfg.programsLabel||'Programs';}
      progs.forEach(function(t){add(pg,t,'p:'+t);});
      if(teams.length){sel.appendChild(pg);}
    }
  }

  function isProg(e){return e.kind==='prog'||e.kind==='deadline';}
  function visible(){
    if(!state.team){return events;}
    var v=state.team.slice(2);
    if(state.team.indexOf('p:')===0){return events.filter(function(e){return e.grp===v;});} // program dates + its own schedule
    return events.filter(function(e){return !isProg(e)&&e.teams.some(function(t){return t.n===v;});});
  }
  function teamName(){return state.team?state.team.slice(2):'';}
  function markText(e){return e.kind==='deadline'?'Registration closes':(e.mark==='start'?'Starts':(e.mark==='end'?'Ends':''));}
  function curTeam(){return state.team.indexOf('t:')===0?state.team.slice(2):'';}
  function teamTime(e){var t=e.teams.filter(function(x){return x.n===curTeam();})[0];return t&&t.t?t.t:'';}
  // Short time line (event chips, day cards, rail).
  function shortWhen(e){
    if(isProg(e)){var m=markText(e);if(m){return m;}return e.time||(e.times?'Times vary':'All day');}
    return (curTeam()&&teamTime(e))||e.time||(e.times?'Times vary':'All day');
  }
  function whenText(e){
    if(isProg(e)){var m=markText(e);return m?(e.kind==='deadline'?m:'Season '+m.toLowerCase())+(e.time?' · '+e.time:''):(e.time||(e.times?'Times vary by group':'All day'));}
    return (curTeam()&&teamTime(e))||e.time||(e.times?'Times vary by team':'All day');
  }
  function byDay(list){var m={};list.forEach(function(e){(m[e.d]=m[e.d]||[]).push(e);});return m;}
  function teamsLine(e,teams){var n=teams.length;if(!n||curTeam()||(isProg(e)&&n===1)){return '';}return n===1?teams[0].n:n+(isProg(e)?' groups':' teams');}

  var upcomingAll=events.filter(function(e){return e.d>=today;});
  var first=(upcomingAll[0]||events[0]||{d:today}).d.slice(0,7), last=(events.length?events[events.length-1].d:today).slice(0,7);
  if(first>last){last=first;}
  state.ym=first;

  // Consecutive days of the same event at the same place read as one entry ("Oct 17-18").
  function spans(list){
    var out=[],open={};
    list.forEach(function(e){
      var key=e.title+'\n'+e.loc+'\n'+(e.kind||''), p=open[key];
      if(p&&!markText(e)&&!markText(p.e)&&addDays(p.end,1)===e.d){
        p.end=e.d;p.all.push(e);
        e.teams.forEach(function(t){if(!p.teams.some(function(x){return x.n===t.n;})){p.teams.push(t);}});
        if(p.time!==(e.time||'')){p.time='';}
        return;
      }
      var s={e:e,end:e.d,teams:e.teams.slice(),time:e.time||'',all:[e]};
      open[key]=s;out.push(s);
    });
    return out;
  }
  function spanOf(e){
    var hit=null;spans(visible()).some(function(s){if(s.all.indexOf(e)>=0){hit=s;return true;}return false;});
    return hit||{e:e,end:e.d,teams:e.teams.slice(),time:e.time||'',all:[e]};
  }
  function idx(e){return events.indexOf(e);}
  function within(s){return s.e.d<=today&&today<=s.end;}
  function dblock(s,list){
    var d=parse(s.e.d),end=parse(s.end),multi=s.end!==s.e.d,t=within(s);
    var num=multi?(d.getMonth()===end.getMonth()?d.getDate()+'–'+end.getDate():d.getDate()+'+'):d.getDate();
    var small=list?(multi?DOW[d.getDay()]+'–'+DOW[end.getDay()]:DOW[d.getDay()]):MONTHS[d.getMonth()].slice(0,3);
    return '<span class="dslc-dblock'+(multi?' is-multi':'')+(t?' is-today':'')+'" aria-hidden="true"><b>'+num+'</b><small>'+small+'</small></span>';
  }
  function spanWhen(s){
    if(isProg(s.e)||s.end===s.e.d){return whenText(s.e);}
    return (curTeam()&&teamTime(s.e))||s.time||'All days';
  }
  function upItem(s,where){
    var e=s.e,d=parse(e.d),tl=teamsLine(e,s.teams);
    return '<button type="button" class="'+UI+'dslc-up '+cls(e)+'" data-i="'+idx(e)+'" data-k="'+where+idx(e)+'">'+dblock(s,false)+
      '<span class="dslc-utext">'+(within(s)?'<span class="dslc-utoday">Today</span>':'')+'<span class="dslc-uname">'+esc(e.title)+'</span>'+
      '<span class="dslc-uwhen">'+DOW[d.getDay()]+' · '+esc(isProg(e)?shortWhen(e):spanWhen(s))+(tl?' · '+esc(tl):'')+'</span></span></button>';
  }
  function teamsHtml(teams,prog,vary){
    if(!cfg.showTeams||!teams.length){return '';}
    var ul='<ul class="dslc-teams">'+teams.map(function(t){return '<li>'+esc(t.n)+(prog&&vary&&t.t?' <span>· '+esc(t.t)+'</span>':'')+'</li>';}).join('')+'</ul>';
    return teams.length>cfg.collapseAfter?'<details class="dslc-tm"><summary>'+teams.length+(prog?' groups':' teams')+'</summary>'+ul+'</details>':ul;
  }
  function rowHtml(s){
    var e=s.e,t=within(s),prog=isProg(e);
    return '<div class="dslc-lrow '+cls(e)+'" data-day="'+e.d+'">'+dblock(s,true)+
      '<div class="dslc-lmain"><button type="button" class="'+UI+'dslc-lbtn" data-i="'+idx(e)+'" data-k="l'+idx(e)+'">'+(t?'<span class="dslc-utoday">Today</span>':'')+
      '<span class="dslc-lname">'+esc(e.title)+'</span><span class="dslc-lwhen">'+esc(spanWhen(s))+(e.loc?' · '+esc(e.loc):'')+'</span></button>'+
      (curTeam()&&!prog?'':teamsHtml(s.teams,prog,!!e.times))+'</div>'+
      '<span class="dslc-badge">'+esc(e.label||e.type)+'</span>'+
      '<button type="button" class="'+UI+'dslc-btn dslc-details" data-i="'+idx(e)+'" data-k="d'+idx(e)+'" aria-label="Details: '+esc(e.title)+'">Details</button></div>';
  }
  function chip(e,day){
    var d=parse(day);
    return '<button type="button" class="'+UI+'dslc-chip '+cls(e)+'" data-i="'+idx(e)+'" data-k="c'+idx(e)+'" aria-label="'+esc(e.title+', '+DAYS[d.getDay()]+' '+MONTHS[d.getMonth()]+' '+d.getDate()+', '+shortWhen(e))+'">'+
      '<span class="dslc-ct">'+esc(shortWhen(e))+'</span><span class="dslc-cn">'+esc(e.title)+'</span></button>';
  }
  function dayCard(e){
    var tl=teamsLine(e,e.teams);
    return '<button type="button" class="'+UI+'dslc-dcard '+cls(e)+'" data-i="'+idx(e)+'" data-k="k'+idx(e)+'"><span><span class="dslc-ct">'+esc(whenText(e))+(tl?' · '+esc(tl):'')+'</span><span class="dslc-cn">'+esc(e.title)+'</span>'+(e.loc?'<span class="dslc-uwhen">'+esc(e.loc)+'</span>':'')+'</span>'+ICON.chev+'</button>';
  }
  function rail(where){
    var ups=spans(visible().filter(function(e){return e.d>=today;})).slice(0,cfg.railCount||5);
    var h=ups.map(function(s){return upItem(s,where);}).join('');
    var none='<p class="'+(where==='r'?'dslc-none':'dslc-note')+'">Nothing coming up'+(state.team?' for '+esc(teamName()):'')+'.</p>';
    return h||none;
  }

  function renderMonth(){
    if(nav){nav.hidden=false;}
    title.textContent=label(state.ym);
    prev.disabled=state.ym<=first; next.disabled=state.ym>=last;
    var p=state.ym.split('-'), start=new Date(+p[0],+p[1]-1,1), month=start.getMonth();
    var vis=visible(), map=byDay(vis), max=cfg.maxChips||3, inMonth=vis.filter(function(e){return e.d.slice(0,7)===state.ym;});
    if(count){count.textContent=inMonth.length+(inMonth.length===1?' event':' events');}
    var cursor=new Date(start);cursor.setDate(1-start.getDay());
    var cells=[];
    for(var i=0;i<42;i++){
      var out=cursor.getMonth()!==month;
      if(i===35&&out){break;}
      cells.push({key:ymd(cursor),n:cursor.getDate(),out:out});
      cursor.setDate(cursor.getDate()+1);
    }
    var h='<div class="dslc-cbody'+(cfg.rail?'':' no-rail')+'"><div class="dslc-main"><div class="dslc-full"><div class="dslc-dowrow">';
    DOW.forEach(function(d){h+='<div class="dslc-dow">'+d+'</div>';});
    h+='</div>';
    for(var w=0;w<cells.length;w+=7){
      var week=cells.slice(w,w+7), any=week.some(function(c){return !c.out&&map[c.key];});
      h+='<div class="dslc-week'+(any?'':' is-empty')+'">';
      week.forEach(function(c){
        var list=c.out?[]:(map[c.key]||[]), all=state.open===c.key;
        h+='<div class="dslc-cell'+(c.out?' is-out':'')+(c.key===today?' is-today':'')+'"><span class="dslc-num"'+(c.key===today?' aria-label="Today, '+c.n+'"':'')+'>'+c.n+'</span>';
        (all?list:list.slice(0,max)).forEach(function(e){h+=chip(e,c.key);});
        if(list.length>max){h+='<button type="button" class="'+UI+'dslc-more" data-more="'+c.key+'" data-k="m'+c.key+'" aria-expanded="'+all+'">'+(all?'Show less':'+ '+(list.length-max)+' more')+'</button>';}
        h+='</div>';
      });
      h+='</div>';
    }
    h+='</div>';
    if(!inMonth.length){h+='<p class="dslc-mempty">'+(state.team?'No events for '+esc(teamName())+' this month.':'No events this month.')+'</p>';}

    // Phone width: mini month + the picked day.
    var pick=state.day&&state.day.slice(0,7)===state.ym?state.day:(today.slice(0,7)===state.ym?today:((inMonth[0]||{}).d||state.ym+'-01'));
    h+='<div class="dslc-compact"><div class="dslc-mini"><div class="dslc-minidow" aria-hidden="true">'+DOW.map(function(d){return '<div>'+d.charAt(0)+'</div>';}).join('')+'</div><div class="dslc-minigrid">';
    cells.forEach(function(c){
      if(c.out){h+='<span aria-hidden="true"></span>';return;}
      var list=map[c.key]||[], dd=parse(c.key);
      h+='<button type="button" class="'+UI+'dslc-mday'+(list.length?' has':'')+(c.key===pick?' is-sel':'')+(c.key===today?' is-today':'')+'" data-day="'+c.key+'" data-k="n'+c.key+'" aria-pressed="'+(c.key===pick)+'" aria-label="'+esc(DAYS[dd.getDay()]+' '+MONTHS[dd.getMonth()]+' '+c.n+(c.key===today?', today':'')+', '+list.length+(list.length===1?' event':' events'))+'">'+
        '<span class="dslc-mn">'+c.n+'</span><span class="dslc-dots">'+list.slice(0,3).map(function(e){return '<i class="'+cls(e)+'"></i>';}).join('')+'</span></button>';
    });
    var pd=parse(pick), dayList=map[pick]||[];
    h+='</div></div><div class="dslc-daylist"><h3 class="dslc-h">'+DAYS[pd.getDay()]+', '+MONTHS[pd.getMonth()]+' '+pd.getDate()+(pick===today?' · Today':'')+'</h3>';
    h+=dayList.length?dayList.map(dayCard).join(''):'<p class="dslc-note">'+(inMonth.length?'Nothing on this day. Days with a dot have events.':(state.team?'No events this month for '+esc(teamName())+'.':'No events this month.'))+'</p>';
    h+='</div></div>';
    if(cfg.rail){
      h+='<div class="dslc-strip"><div class="dslc-strip-head"><h3 class="dslc-h">'+esc(cfg.railTitle)+'</h3>'+(cfg.canSwitch?'<button type="button" class="'+UI+'dslc-link" data-go="list" data-k="sl">Full list →</button>':'')+'</div><div class="dslc-strip-row">'+rail('s')+'</div></div>';
    }
    h+='</div>';
    if(cfg.rail){
      h+='<aside class="dslc-side" aria-label="'+esc(cfg.railTitle)+'"><h3 class="dslc-h">'+esc(cfg.railTitle)+'</h3>'+rail('r')+(cfg.canSwitch?'<button type="button" class="'+UI+'dslc-link" data-go="list" data-k="rl">See the full list →</button>':'')+'</aside>';
    }
    h+='</div>';
    if(!events.length){h+='<div class="dslc-list"><div class="dslc-lempty"><p class="dslc-big">'+esc(cfg.emptyText)+'</p></div></div>';}
    body.innerHTML=h;
  }
  function renderList(){
    if(nav){nav.hidden=true;}
    title.textContent='Upcoming';
    var list=spans(visible().filter(function(e){return e.d>=today;}));
    if(count){count.textContent=list.length+' upcoming';}
    var h='<div class="dslc-list">',cur='';
    list.forEach(function(s){
      var m=s.e.d.slice(0,7);
      if(m!==cur){h+=(cur?'</div></div>':'')+'<div class="dslc-lgroup"><h3 class="dslc-h dslc-lmonth">'+esc(label(m))+'</h3><div class="dslc-lrows">';cur=m;}
      h+=rowHtml(s);
    });
    if(cur){h+='</div></div>';}
    if(!list.length){
      h+='<div class="dslc-lempty"><p class="dslc-big">'+(state.team?'No upcoming events for '+esc(teamName()):esc(cfg.emptyText))+'</p>'+
        (state.team?'<p class="dslc-sub">Their schedule may not be in LeagueApps yet.</p><button type="button" class="'+UI+'dslc-btn" data-clear="1" data-k="cl">Show '+esc((cfg.teamAll||'All teams').toLowerCase())+'</button>':'')+'</div>';
    }
    body.innerHTML=h+'</div>';
  }
  function renderLegend(){
    if(!legend||!root.classList.contains('dslc--by-type')){return;}
    var names={'t-tour':'Tournaments','t-game':'Games','t-other':'Other events'},seen={};
    visible().forEach(function(e){seen[cls(e)]=1;});
    legend.innerHTML=Object.keys(names).filter(function(k){return seen[k];}).map(function(k){return '<span class="'+k+'"><i></i>'+names[k]+'</span>';}).join('');
  }
  function render(){
    var k=document.activeElement&&body.contains(document.activeElement)?document.activeElement.getAttribute('data-k'):null;
    root.querySelectorAll('.dslc-view').forEach(function(b){b.setAttribute('aria-pressed',b.dataset.view===state.view?'true':'false');});
    if(state.view==='month'){renderMonth();}else{renderList();}
    renderLegend();
    if(k){var f=body.querySelector('[data-k="'+k+'"]');if(f){f.focus();}}
  }

  /* ---- Calendar links ---- */
  function entry(s){
    var e=s.e, prog=isProg(e), mk=markText(e), team=curTeam();
    var name=e.kind==='deadline'?'Registration closes: '+e.title:(e.mark==='start'?e.title+' starts':(e.mark==='end'?e.title+' ends':e.title));
    if(team&&!prog){name+=' ('+team+')';}
    var text=team&&!prog?(teamTime(e)||e.time):(s.end===e.d?e.time:s.time);
    var r=mk?null:clock(text,e.d,tz);
    var days=Math.round((parse(s.end)-parse(e.d))/864e5)+1;
    if(r&&r.cross){days=1;}
    var lines=[e.label||e.type];
    if(s.teams.length&&!(team&&!prog)){lines.push((prog?'Groups: ':'Teams: ')+s.teams.map(function(t){return t.n+(e.times&&t.t&&!/\d\s*(?::\d\d)?\s*[ap]m\b/i.test(t.n)?' '+t.t:'');}).join(', '));}
    if(e.note){lines.push(e.note);}
    if(e.reg){lines.push('Register: '+e.reg);}
    if(e.more&&e.more!==e.reg){lines.push('Program details: '+e.more);}
    if(e.url){lines.push('Event website: '+e.url);}
    lines.push('',(cfg.calName?cfg.calName+' calendar: ':'Calendar: ')+location.href.split('#')[0]);
    return {name:name,loc:e.loc||'',desc:lines.filter(function(x,i){return x||i>0;}).join('\n'),r:r,days:days,start:e.d,end:addDays(s.end,1)};
  }
  function googleUrl(x){
    var dates=x.r?zulu(x.r.s)+'/'+zulu(x.r.e):x.start.replace(/-/g,'')+'/'+x.end.replace(/-/g,'');
    return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text='+encodeURIComponent(x.name)+'&dates='+dates+'&details='+encodeURIComponent(x.desc)+'&location='+encodeURIComponent(x.loc)+(x.r&&x.days>1?'&recur='+encodeURIComponent('RRULE:FREQ=DAILY;COUNT='+x.days):'');
  }
  function outlookUrl(x,host){
    var body=x.desc+(x.r&&x.days>1?'\n\nEvery day until '+x.end:'');
    return 'https://'+host+'/calendar/0/deeplink/compose?path=%2Fcalendar%2Faction%2Fcompose&rru=addevent&subject='+encodeURIComponent(x.name)+
      '&startdt='+encodeURIComponent(x.r?x.r.s.toISOString():x.start)+'&enddt='+encodeURIComponent(x.r?x.r.e.toISOString():x.end)+
      '&allday='+(x.r?'false':'true')+'&location='+encodeURIComponent(x.loc)+'&body='+encodeURIComponent(body);
  }
  function icsFile(x){
    var host=location.hostname||'calendar', now=zulu(new Date());
    var L=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Design Shop//DS Toolkit LeagueApps Calendar//EN','CALSCALE:GREGORIAN','METHOD:PUBLISH','BEGIN:VEVENT',
      'UID:'+slug(x.name)+'-'+x.start.replace(/-/g,'')+'@'+host,'DTSTAMP:'+now];
    if(x.r){L.push('DTSTART:'+zulu(x.r.s),'DTEND:'+zulu(x.r.e));if(x.days>1){L.push('RRULE:FREQ=DAILY;COUNT='+x.days);}}
    else{L.push('DTSTART;VALUE=DATE:'+x.start.replace(/-/g,''),'DTEND;VALUE=DATE:'+x.end.replace(/-/g,''));}
    L.push('SUMMARY:'+icsEsc(x.name));
    if(x.loc){L.push('LOCATION:'+icsEsc(x.loc));}
    L.push('DESCRIPTION:'+icsEsc(x.desc),'END:VEVENT','END:VCALENDAR');
    return L.map(fold).join('\r\n')+'\r\n';
  }
  function download(name,text){
    var url=URL.createObjectURL(new Blob([text],{type:'text/calendar;charset=utf-8'}));
    var a=document.createElement('a');a.href=url;a.download=name;a.rel='noopener';a.style.display='none';
    document.body.appendChild(a);a.click();
    setTimeout(function(){URL.revokeObjectURL(url);a.remove();},1500);
  }
  function feedUrl(){return cfg.feed?cfg.feed+(state.team?(cfg.feed.indexOf('?')>=0?'&':'?')+'team='+encodeURIComponent(state.team):''):'';}
  function pi(icon,text,href,act){
    return href?'<a class="dslc-pi" href="'+esc(href)+'"'+(/^https:/.test(href)?' target="_blank" rel="noopener"':'')+'>'+icon+'<span>'+esc(text)+'</span></a>'
      :'<button type="button" class="'+UI+'dslc-pi" data-pi="'+act+'">'+icon+'<span>'+esc(text)+'</span></button>';
  }
  function fillMenu(menu){
    var pop=menu.querySelector('.dslc-pop');
    if(menu.dataset.menu==='sub'){
      var https=feedUrl(), webcal=https.replace(/^https?:/,'webcal:'), name=(cfg.calName||'Calendar')+(state.team?' ('+teamName()+')':'');
      pop.innerHTML='<p class="dslc-pop-h">'+esc(state.team?'Subscribe to '+teamName():'Subscribe to this calendar')+'</p><p class="dslc-pop-s">Adds it to your calendar app and keeps it up to date when the schedule changes.</p>'+
        pi(ICON.google,'Google Calendar','https://calendar.google.com/calendar/render?cid='+encodeURIComponent(webcal))+
        pi(ICON.apple,'Apple Calendar (iPhone, Mac)',webcal)+
        pi(ICON.outlook,'Outlook.com','https://outlook.live.com/calendar/0/addfromweb?url='+encodeURIComponent(https)+'&name='+encodeURIComponent(name))+
        pi(ICON.outlook,'Microsoft 365','https://outlook.office.com/calendar/0/addfromweb?url='+encodeURIComponent(https)+'&name='+encodeURIComponent(name))+
        pi(ICON.link,'Copy the calendar link','','copy');
    }else{
      var x=entry(state.span);
      pop.innerHTML=pi(ICON.google,'Google Calendar',googleUrl(x))+
        pi(ICON.apple,'Apple Calendar','','ics')+
        pi(ICON.outlook,'Outlook.com',outlookUrl(x,'outlook.live.com'))+
        pi(ICON.outlook,'Microsoft 365',outlookUrl(x,'outlook.office.com'))+
        pi(ICON.file,'Other apps (.ics file)','','ics');
    }
  }
  function closeMenus(except){
    var was=false;
    root.querySelectorAll('.dslc-menu').forEach(function(m){
      if(m===except){return;}
      var p=m.querySelector('.dslc-pop'),b=m.querySelector('.dslc-menu-btn');
      if(p&&!p.hidden){p.hidden=true;was=true;if(b){b.setAttribute('aria-expanded','false');}}
    });
    return was;
  }
  function toggleMenu(menu){
    var p=menu.querySelector('.dslc-pop'),b=menu.querySelector('.dslc-menu-btn'),opening=p.hidden;
    closeMenus(menu);
    if(opening){fillMenu(menu);}
    p.hidden=!opening;b.setAttribute('aria-expanded',opening?'true':'false');
    if(opening){var f=p.querySelector('a,button');if(f){f.focus();}}
  }
  function copy(text,btn){
    var done=function(){var s=btn.querySelector('span');if(s){s.textContent='Link copied';}};
    if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(text).then(done,function(){window.prompt('Copy this link',text);});}
    else{window.prompt('Copy this link',text);}
  }

  /* ---- Pop-up ---- */
  function open(e){
    var s=spanOf(e);state.ev=e;state.span=s;
    var d=parse(e.d), end=parse(s.end), multi=s.end!==e.d, prog=isProg(e), tid=dbody.dataset.titleId;
    var map=(e.lat&&e.lng)?'https://www.google.com/maps/search/?api=1&query='+e.lat+','+e.lng:(e.loc?'https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(e.loc):'');
    var date=multi?DOW[d.getDay()]+', '+MONTHS[d.getMonth()].slice(0,3)+' '+d.getDate()+' to '+DOW[end.getDay()]+', '+MONTHS[end.getMonth()].slice(0,3)+' '+end.getDate()+', '+end.getFullYear()
      :DAYS[d.getDay()]+', '+MONTHS[d.getMonth()]+' '+d.getDate()+', '+d.getFullYear();
    var word=prog?'group':'team', teams=cfg.showTeams&&!(curTeam()&&!prog)?s.teams:[];
    var tl=teams.map(function(t){return '<li>'+esc(t.n)+((e.times||prog)&&t.t&&!e.time?' <span>· '+esc(t.t)+'</span>':'')+'</li>';}).join('');
    var acts=[], fill=e.reg&&cfg.registerText?'reg':(e.url&&cfg.websiteText?'web':(e.more&&cfg.detailsText?'more':''));
    var link=function(k,href,text){return '<a class="dslc-act'+(fill===k?' is-fill':'')+'" href="'+esc(href)+'" target="_blank" rel="noopener">'+esc(text)+'</a>';};
    if(cfg.addText){acts.push('<div class="dslc-menu" data-menu="add"><button type="button" class="'+UI+'dslc-act dslc-menu-btn" aria-expanded="false" aria-haspopup="true">'+ICON.add+'<span>'+esc(cfg.addText)+'</span></button><div class="dslc-pop" hidden></div></div>');}
    if(map&&cfg.directionsText){acts.push(link('map',map,cfg.directionsText));}
    var tail=[];
    if(e.more&&cfg.detailsText&&e.more!==e.reg){tail.push(['more',link('more',e.more,cfg.detailsText)]);}
    if(e.url&&cfg.websiteText){tail.push(['web',link('web',e.url,cfg.websiteText)]);}
    if(e.reg&&cfg.registerText){tail.push(['reg',link('reg',e.reg,cfg.registerText)]);}
    tail.sort(function(a,b){return (a[0]===fill)-(b[0]===fill);}).forEach(function(t){acts.push(t[1]);}); // the main button last (top on phones)
    dbody.innerHTML=
      '<div class="dslc-dlg-top"><div><span class="dslc-badge '+cls(e)+'">'+esc(e.label||e.type)+'</span><h3 id="'+esc(tid)+'" class="dslc-h dslc-dlg-title">'+esc(e.title)+'</h3></div>'+
      '<button type="button" class="'+UI+'dslc-close" aria-label="Close">'+ICON.close+'</button></div>'+
      '<div class="dslc-info"><div>'+ICON.cal+'<div><div class="dslc-a">'+esc(date)+'</div><div class="dslc-b">'+esc(isProg(e)?whenText(e):spanWhen(s))+(within(s)?' · Today':'')+'</div></div></div>'+
      (e.loc?'<div>'+ICON.pin+'<div><div class="dslc-a">'+esc(e.loc)+'</div>'+(map?'<div class="dslc-b">Directions open Google Maps</div>':'')+'</div></div>':'')+
      (tl?'<div class="is-wide">'+ICON.users+'<div><div class="dslc-a">'+teams.length+' '+word+(teams.length>1?'s':'')+'</div><ul class="dslc-teams">'+tl+'</ul></div></div>':'')+
      (e.note?'<div class="is-wide">'+ICON.info+'<div class="dslc-b">'+esc(e.note)+'</div></div>':'')+'</div>'+
      (acts.length?'<div class="dslc-actions">'+acts.join('')+'</div>':'');
    if(dlg.showModal){if(!dlg.open){dlg.showModal();}}else{dlg.setAttribute('open','');}
    var c=dbody.querySelector('.dslc-close');if(c){c.focus();}
  }
  var opener=null;
  function shut(){closeMenus();if(dlg.close&&dlg.open){dlg.close();}else{dlg.removeAttribute('open');}if(opener&&document.contains(opener)){opener.focus();}opener=null;}

  root.addEventListener('click',function(ev){
    var t=ev.target;
    var pitem=t.closest('.dslc-pi');
    if(pitem){
      var act=pitem.getAttribute('data-pi');
      if(act==='copy'){copy(feedUrl(),pitem);return;}
      if(act==='ics'){download(slug(state.span.e.title)+'.ics',icsFile(entry(state.span)));}
      closeMenus();return;
    }
    var mb=t.closest('.dslc-menu-btn'); if(mb){toggleMenu(mb.closest('.dslc-menu'));return;}
    if(!t.closest('.dslc-pop')){closeMenus();}
    if(t.closest('.dslc-close')){shut();return;}
    var item=t.closest('[data-i]');
    if(item&&body.contains(item)){opener=item;open(events[+item.dataset.i]);return;}
    var more=t.closest('[data-more]');
    if(more){state.open=state.open===more.dataset.more?null:more.dataset.more;render();return;}
    var mday=t.closest('.dslc-mday');
    if(mday){state.day=mday.dataset.day;render();return;}
    var go=t.closest('[data-go]');
    if(go){state.view=go.dataset.go;render();if(title.scrollIntoView){title.scrollIntoView({block:'nearest'});}return;}
    if(t.closest('[data-clear]')){state.team='';if(sel){sel.value='';}render();return;}
    var v=t.closest('.dslc-view');
    if(v){state.view=v.dataset.view;render();return;}
    if(t.closest('.dslc-today')){var tm=today.slice(0,7);state.ym=tm<first?first:(tm>last?last:tm);state.day=today;state.open=null;state.view='month';render();}
  });
  document.addEventListener('click',function(ev){if(!root.contains(ev.target)){closeMenus();}});
  root.addEventListener('keydown',function(ev){
    if(ev.key==='Escape'&&closeMenus()){ev.preventDefault();ev.stopPropagation();var b=ev.target.closest('.dslc-menu');b=b&&b.querySelector('.dslc-menu-btn');if(b){b.focus();}}
  });
  dlg.addEventListener('cancel',function(ev){if(closeMenus()){ev.preventDefault();}});
  dlg.addEventListener('close',function(){closeMenus();});
  prev.addEventListener('click',function(){if(state.ym>first){state.ym=shift(state.ym,-1);state.open=null;render();}});
  next.addEventListener('click',function(){if(state.ym<last){state.ym=shift(state.ym,1);state.open=null;render();}});
  if(sel){sel.addEventListener('change',function(){state.team=sel.value;closeMenus();render();});}
  dlg.addEventListener('click',function(ev){if(ev.target===dlg){shut();}});
  if(!dlg.showModal){document.addEventListener('keydown',function(ev){if('Escape'===ev.key&&dlg.hasAttribute('open')){shut();}});}
  render();
}

window.DSLACalendar={init:init};
function all(){document.querySelectorAll('.dslc').forEach(init);}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',all);}else{all();}
})();
