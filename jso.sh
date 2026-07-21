if [ -z "$1" ]; then
echo "provide the jso link";
exit
fi
echo 'fetch("'$1'").then(r=>r.text()).then(d=>{document.body.innerHTML=d;});' > p.js