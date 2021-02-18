<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>查看照片</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div id="app">
    <div class="row">
        <div class="col-xs-offset-2 col-xs-8">
            <div class="page-header">
                <h2>Router Basic - 01</h2>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-2 col-xs-offset-2">
            <div class="list-group">
                <!--使用指令v-link进行导航-->
                <router-link to='/setConfig' class="list-group-item"  >人脸识别-设备配置</router-link>
            </div>
        </div>
        <div class="col-xs-6">
            <div class="panel">
                <div class="panel-body">
                    <!--用于渲染匹配的组件-->
                    <router-view></router-view>
                </div>
            </div>
        </div>
    </div>
</div>
<template id="setConfig">
    <div>
        @foreach($ip_arr as $ip)
            <a href="/photo?ip={{$ip}}&id={{$id}}">{{$ip}}---{{$id}}</a><br>
        @endforeach
        @foreach($photo_arr['data'] as $item)
            <div style="display: inline-block">
                <img src="data:image/png;base64,{{$item['imgBase64']}}" style="height: 100px" alt="">
                <div style="width: 100px;overflow-wrap: break-word;">faceId:{{$item['faceId']}}</div>
            </div>
        @endforeach
    </div>
</template>
<template id="about">
    <div>
        <h1>About</h1>
        <p>@{{msg}}</p>
        <p>@{{num}}</p>
    </div>
</template>
</body>
<script src="https://cdn.bootcss.com/vue/2.5.17-beta.0/vue.js"></script>
<script src="https://cdn.bootcss.com/vue-router/3.0.1/vue-router.js"></script>
<script src="https://cdn.bootcss.com/vuex/3.0.1/vuex.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<script>
    const store = new Vuex.Store({
        state: {
            count: 5
        },
        mutations: {
            increment (state, no) {
                state.count = Number(state.count) + Number(no)
            }
        },
        actions: {
            incrementAsync  (context,payload) {
                context.commit('increment',payload.amount)
            }
        }
    })

    /* 创建组件构造器  */
    const SetConfig = Vue.extend({
        template: '#setConfig',
        data: function () {
            return {
                ip:'192.168.30.8',
                config:{
                    comModType:1,
                    comModContent:'hello',
                    companyName:'三叶草智慧校园',
                    delayTimeForCloseDoor:500,
                    displayModType:1,
                    displayModContent:'{name}',
                    identifyDistance:2,
                    saveIdentifyTime:3,
                    identifyScores:85,
                    multiplayerDetection:2,
                    recRank:3,
                    recStrangerTimesThreshold:3,
                    recStrangerType:1,
                    ttsModStrangerType:1,
                    ttsModStrangerContent:'陌生人',
                    ttsModType:1,
                    ttsModContent:'{name}',
                    wg:'#WG{id}#',
                    whitelist:1,
                    saveIdentifyMode:1,
                    onLightStartTime:0,
                    onLightEndTime:0
                },

            }
        },
        created:function(){
            this.num = store.state.count
            console.log( this.num ) // -> 1
        },
        methods:{
            _alert:function (e){

                console.log(this.$data);
                var params=this.$data
                axios.post('{{route('face.setConfig')}}',params)
                    .then(function (response) {
                        alert(response.data.data.msg)
                    })
                    .catch(function (error) {
                        alert(error);
                    });
            }
        },
    })
    /* About 路由的组件 */
    const About = Vue.extend({
        template: '#about',
        data: function () {
            return {
                msg: 'Hello, vue router6666666666!',
                num: store.state.count
            }
        },
        methods:{
            _alert:function(e){
                alert(e)
            }
        },
    })

    /* 创建路由映射  */
    const routes = [
        { path: '/setConfig', component: SetConfig},
        { path: '/about', component: About },
        { path: '/', component: SetConfig }
    ]

    /* 创建路由器  */
    var router = new VueRouter({
        routes
    })

    const vm = new Vue({
        // el: 'body',
        router,
        components: { SetConfig, About },
        template: '#app'
    })

    /* 启动路由  */
    const app = new Vue({
        router
    }).$mount('#app')
</script>
</html>