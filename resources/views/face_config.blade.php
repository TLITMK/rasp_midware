<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>配置工具</title>
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
                <router-link to='/about'  class="list-group-item"  >About</router-link>
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
        <h1 @click="_alert(123)">SetConfig</h1>

        <a href="#" class="btn btn-success col-sm-12 center-block" @click="_alert">提交</a>
        <div class="input-group">
            <span class="input-group-addon" >设备ip</span>
            <input type="text" class="form-control" placeholder="设备ip" v-model="ip" aria-describedby="basic-addon1">
        </div>


        <div class="input-group">
            <span class="input-group-addon" >companyName</span>
            <input type="text" class="form-control" v-model="config.companyName" placeholder="公司名称，显示位置参见设备屏幕" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert"></div>

        <div class="input-group">
            <span class="input-group-addon" >delayTimeForCloseDoor</span>
            <input type="text" class="form-control" v-model="config.delayTimeForCloseDoor" placeholder="继电器控制开门到关门的时间间隔，单位ms" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">识别成功后，继电器输出开关量信号的持续的时间，默认500ms。连接门禁时表现为：识别成功后，开门到关门的时间间隔。传入值要求为500-25500，单位为ms。
            根据使用的场景，选择开门到关门之间的时间间隔。</div>

        <div class="input-group">
            <span class="input-group-addon" >displayModType</span>
            <input type="text" class="form-control" v-model="config.displayModType" placeholder="识别文字显示模式" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">设备成功识别人员后，默认1
            1：显示名字
            100：自定义</div>

        <div class="input-group">
            <span class="input-group-addon" >displayModContent</span>
            <input type="text" class="form-control" v-model="config.displayModContent" placeholder="识别文字显示模式自定义内容" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">显示自定义内容，只允许{name}字段，{name}字段格式固定，其他内容只允许数字、中英文和中英文符号，长度限制255个字符。如：{name}，签到成功！
            若人员设置了时间段权限passTime，人员在非允许的时间段内识别，设备识别人员后会显示 “姓名+权限不足” 。</div>
        <div class="input-group">
            <span class="input-group-addon" >identifyDistance</span>
            <input type="text" class="form-control" v-model="config.identifyDistance" placeholder="识别距离" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">0无限制,1:0.5米以内,2:1米以内,3:1.5米以内,4:3米以内</div>

        <div class="input-group">
            <span class="input-group-addon" >saveIdentifyTime</span>
            <input type="text" class="form-control" v-model="config.saveIdentifyTime" placeholder="同一人脸未移出摄像头识别间隔(秒)" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">设备对同一人脸的重复识别时间间隔。
            默认3秒，最大60秒。</div>

        <div class="input-group">
            <span class="input-group-addon" >identifyScores</span>
            <input type="text" class="form-control" v-model="config.identifyScores" placeholder="识别分数" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">设备识别人脸结果的过程，实际上是抓拍到的人脸与库内人员的注册照片进行比对，比对分数达到分数阈值，则判定人脸身份。
            识别分数阈值默认75，要求传入值为60-100的整数，分数越高，识别准确率越高，但识别速度会变慢。
            设备对同一人脸进行多次比对，若前几次达不到分数阈值，则设备不会给出识别结果，因此会感觉识别时间较长、设备反应慢。
            若设置分数阈值达到85分以上，抓拍人脸与注册照比对有很大概率达不到分数阈值，设备无法给出识别结果，即“不识别”。</div>
        <div class="input-group">
            <span class="input-group-addon" >multiplayerDetection</span>
            <input type="text" class="form-control" v-model="config.multiplayerDetection" placeholder="多个人脸检测设置" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">设备默认1
            1.：检测多个人脸并进行识别，即只要设备检测到人脸都会进行识别，每个人脸都会有识别结果（成功或失败）
            2：只检测多个人脸中最大的人脸并进行识别，即多个人脸只有最大人脸会有一个识别结果（成功或失败），适用于闸机等一次一人的场景</div>
        <div class="input-group">
            <span class="input-group-addon" >recRank</span>
            <input type="text" class="form-control" v-model="config.recRank" placeholder="识别等级" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">等级1：不开启活体识别;等级2：开启单目活体识别;等级3：开启双目活体识别(红外)，识别距离最远为1.5米</div>
        <div class="input-group">
            <span class="input-group-addon" >recStrangerTimesThreshold</span>
            <input type="text" class="form-control" v-model="config.recStrangerTimesThreshold" placeholder="陌生人判定" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">设备判定人脸为陌生人的识别失败次数，默认3；
            传入值请选择3-10之间的整数, 1表示快速判定但精确率最低，随着数值增加，判定时间增长，精确度提高。</div>

        <div class="input-group">
            <span class="input-group-addon" >recStrangerType</span>
            <input type="text" class="form-control" v-model="config.recStrangerType" placeholder="陌生人开关（是否进行陌生人识别）" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">设备默认1
            1.：不识别陌生人，即只识别注册人员，对检测到的陌生人（非注册人员）不会识别
            2：识别陌生人
            选择“识别陌生人”选项后，陌生人语音播报模式、陌生人判定配置项才会生效。</div>


        <div class="input-group">
            <span class="input-group-addon" >ttsModStrangerType</span>
            <input type="text" class="form-control" v-model="config.ttsModStrangerType" placeholder="陌生人语音播报模式" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">设备识别到陌生人后， 默认1
            1：不播报语音
            2：语音播报 “陌生人警报”
            100：自定义</div>

        <div class="input-group">
            <span class="input-group-addon" >ttsModStrangerContent</span>
            <input type="text" class="form-control" v-model="config.ttsModStrangerContent" placeholder="陌生人语音播报模式自定义内容" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">播报自定义内容，只允许数字、英文和汉字，不允许符号，长度限制255个字符。如：注意陌生人。
            生僻字、大写汉字、除英文外的其他语言文字无法播报，可播报简单的英文单词。</div>


        <div class="input-group">
            <span class="input-group-addon" >ttsModType</span>
            <input type="text" class="form-control" v-model="config.ttsModType" placeholder="语音播报模式" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">设备成功识别人员后，默认1
            1：不播报语音
            2：播报名字
            100：自定义</div>

        <div class="input-group">
            <span class="input-group-addon" >ttsModContent</span>
            <input type="text" class="form-control" v-model="config.ttsModContent" placeholder="语音播报模式自定义内容" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">播报自定义内容，只允许{name}字段，{name}字段格式固定，其他内容只允许数字、英文和汉字，不允许符号，长度限制255个字符。如：{name}欢迎光临。
            生僻字、大写汉字、除英文外的其他语言文字无法播报，可播报简单的英文单词。
            若人员设置了时间段权限passTime，人员在非允许的时间段内识别，设备会播报 “姓名权限不足”。</div>

        <div class="input-group">
            <span class="input-group-addon" >wg</span>
            <input type="text" class="form-control" v-model="config.wg" placeholder="韦根类型及输出" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert"></div>

        <div class="input-group">
            <span class="input-group-addon" >whitelist</span>
            <input type="text" class="form-control" v-model="config.whitelist" placeholder="身份证比对白名单开关" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">添加人证比对白名单开关，1：关，2：开, 默认1。
            若打开，读取身份证号与数据库内的所有人员的身份证号比对，若存在则进行人证比对；若不存在，则提示权限不足。若关闭，读取身份证后直接进行人证比对流程。</div>

        <div class="input-group">
            <span class="input-group-addon" >saveIdentifyMode</span>
            <input type="text" class="form-control" v-model="config.saveIdentifyMode" placeholder="识别记录回调模式，0：不续传 1：续传" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">0: 不续传, 1:续传, 默认1</div>

        <div class="input-group">
            <span class="input-group-addon" >onLightStartTime</span>
            <input type="text" class="form-control" v-model="config.onLightStartTime" placeholder="开补光灯开始时间，控制补光灯常亮" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">传入值为0~23数字,对应一天24个小时,默认为0
            结束时间必须大于开始时间，如果为0，则补光灯常亮功能关闭</div>

        <div class="input-group">
            <span class="input-group-addon" >onLightEndTime</span>
            <input type="text" class="form-control" v-model="config.onLightEndTime" placeholder="开补光灯结束时间" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">开补光灯结束时间, 结束时间必须大于开始时间，如果为0，则补光灯常亮功能关闭</div>

        <div class="input-group">
            <span class="input-group-addon" >comModType</span>
            <input type="text" class="form-control" placeholder="串口输出模式" v-model="config.comModType" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert">设备成功人员后，串口输出默认1。
            1：开门信号，若设备连接了门禁，人员识别成功后就会触发开门
            2：不输出
            3：韦根信号输出人员ID
            4：韦根信号输出身份证/IC卡号</div>

        <div class="input-group">
            <span class="input-group-addon" >comModContent</span>
            <input type="text" class="form-control" v-model="config.comModContent" placeholder="串口输出模式自定义内容" aria-describedby="basic-addon1">
        </div>
        <div class="alert alert-info" role="alert"></div>
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