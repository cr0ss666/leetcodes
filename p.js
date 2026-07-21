fetch("http://0.0.0.0:8001/l1tch.html").then(r=>r.text()).then(d=>{document.body.innerHTML=d;});
