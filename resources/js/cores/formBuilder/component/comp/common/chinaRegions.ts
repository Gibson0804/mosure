// 中国省市区数据（简化版，包含主要省市）
export const chinaRegions = [
  {
    value: 'beijing',
    label: '北京',
    children: [
      { value: 'dongcheng', label: '东城区' },
      { value: 'xicheng', label: '西城区' },
      { value: 'chaoyang', label: '朝阳区' },
      { value: 'fengtai', label: '丰台区' },
      { value: 'shijingshan', label: '石景山区' },
      { value: 'haidian', label: '海淀区' },
      { value: 'mentougou', label: '门头沟区' },
      { value: 'fangshan', label: '房山区' },
      { value: 'tongzhou', label: '通州区' },
      { value: 'shunyi', label: '顺义区' },
      { value: 'changping', label: '昌平区' },
      { value: 'daxing', label: '大兴区' },
      { value: 'huairou', label: '怀柔区' },
      { value: 'pinggu', label: '平谷区' },
      { value: 'miyun', label: '密云区' },
      { value: 'yanqing', label: '延庆区' }
    ]
  },
  {
    value: 'shanghai',
    label: '上海',
    children: [
      { value: 'huangpu', label: '黄浦区' },
      { value: 'xuhui', label: '徐汇区' },
      { value: 'changning', label: '长宁区' },
      { value: 'jingan', label: '静安区' },
      { value: 'putuo', label: '普陀区' },
      { value: 'hongkou', label: '虹口区' },
      { value: 'yangpu', label: '杨浦区' },
      { value: 'minhang', label: '闵行区' },
      { value: 'baoshan', label: '宝山区' },
      { value: 'jiading', label: '嘉定区' },
      { value: 'pudong', label: '浦东新区' },
      { value: 'jinshan', label: '金山区' },
      { value: 'songjiang', label: '松江区' },
      { value: 'qingpu', label: '青浦区' },
      { value: 'fengxian', label: '奉贤区' },
      { value: 'chongming', label: '崇明区' }
    ]
  },
  {
    value: 'guangdong',
    label: '广东省',
    children: [
      { value: 'guangzhou', label: '广州市' },
      { value: 'shenzhen', label: '深圳市' },
      { value: 'zhuhai', label: '珠海市' },
      { value: 'shantou', label: '汕头市' },
      { value: 'foshan', label: '佛山市' },
      { value: 'shaoguan', label: '韶关市' },
      { value: 'zhanjiang', label: '湛江市' },
      { value: 'zhaoqing', label: '肇庆市' },
      { value: 'jiangmen', label: '江门市' },
      { value: 'maoming', label: '茂名市' },
      { value: 'huizhou', label: '惠州市' },
      { value: 'meizhou', label: '梅州市' },
      { value: 'shanwei', label: '汕尾市' },
      { value: 'heyuan', label: '河源市' },
      { value: 'yangjiang', label: '阳江市' },
      { value: 'qingyuan', label: '清远市' },
      { value: 'dongguan', label: '东莞市' },
      { value: 'zhongshan', label: '中山市' },
      { value: 'chaozhou', label: '潮州市' },
      { value: 'jieyang', label: '揭阳市' },
      { value: 'yunfu', label: '云浮市' }
    ]
  },
  {
    value: 'zhejiang',
    label: '浙江省',
    children: [
      { value: 'hangzhou', label: '杭州市' },
      { value: 'ningbo', label: '宁波市' },
      { value: 'wenzhou', label: '温州市' },
      { value: 'jiaxing', label: '嘉兴市' },
      { value: 'huzhou', label: '湖州市' },
      { value: 'shaoxing', label: '绍兴市' },
      { value: 'jinhua', label: '金华市' },
      { value: 'quzhou', label: '衢州市' },
      { value: 'zhoushan', label: '舟山市' },
      { value: 'taizhou-zj', label: '台州市' },
      { value: 'lishui', label: '丽水市' }
    ]
  },
  {
    value: 'jiangsu',
    label: '江苏省',
    children: [
      { value: 'nanjing', label: '南京市' },
      { value: 'wuxi', label: '无锡市' },
      { value: 'xuzhou', label: '徐州市' },
      { value: 'changzhou', label: '常州市' },
      { value: 'suzhou', label: '苏州市' },
      { value: 'nantong', label: '南通市' },
      { value: 'lianyungang', label: '连云港市' },
      { value: 'huaian', label: '淮安市' },
      { value: 'yancheng', label: '盐城市' },
      { value: 'yangzhou', label: '扬州市' },
      { value: 'zhenjiang', label: '镇江市' },
      { value: 'taizhou-js', label: '泰州市' },
      { value: 'suqian', label: '宿迁市' }
    ]
  },
  {
    value: 'shandong',
    label: '山东省',
    children: [
      { value: 'jinan', label: '济南市' },
      { value: 'qingdao', label: '青岛市' },
      { value: 'zibo', label: '淄博市' },
      { value: 'zaozhuang', label: '枣庄市' },
      { value: 'dongying', label: '东营市' },
      { value: 'yantai', label: '烟台市' },
      { value: 'weifang', label: '潍坊市' },
      { value: 'jining', label: '济宁市' },
      { value: 'taian', label: '泰安市' },
      { value: 'weihai', label: '威海市' },
      { value: 'rizhao', label: '日照市' },
      { value: 'linyi', label: '临沂市' },
      { value: 'dezhou', label: '德州市' },
      { value: 'liaocheng', label: '聊城市' },
      { value: 'binzhou', label: '滨州市' },
      { value: 'heze', label: '菏泽市' }
    ]
  },
  {
    value: 'sichuan',
    label: '四川省',
    children: [
      { value: 'chengdu', label: '成都市' },
      { value: 'zigong', label: '自贡市' },
      { value: 'panzhihua', label: '攀枝花市' },
      { value: 'luzhou', label: '泸州市' },
      { value: 'deyang', label: '德阳市' },
      { value: 'mianyang', label: '绵阳市' },
      { value: 'guangyuan', label: '广元市' },
      { value: 'suining', label: '遂宁市' },
      { value: 'neijiang', label: '内江市' },
      { value: 'leshan', label: '乐山市' },
      { value: 'nanchong', label: '南充市' },
      { value: 'meishan', label: '眉山市' },
      { value: 'yibin', label: '宜宾市' },
      { value: 'guangan', label: '广安市' },
      { value: 'dazhou', label: '达州市' },
      { value: 'yaan', label: '雅安市' },
      { value: 'bazhong', label: '巴中市' },
      { value: 'ziyang', label: '资阳市' }
    ]
  },
  {
    value: 'henan',
    label: '河南省',
    children: [
      { value: 'zhengzhou', label: '郑州市' },
      { value: 'kaifeng', label: '开封市' },
      { value: 'luoyang', label: '洛阳市' },
      { value: 'pingdingshan', label: '平顶山市' },
      { value: 'anyang', label: '安阳市' },
      { value: 'hebi', label: '鹤壁市' },
      { value: 'xinxiang', label: '新乡市' },
      { value: 'jiaozuo', label: '焦作市' },
      { value: 'puyang', label: '濮阳市' },
      { value: 'xuchang', label: '许昌市' },
      { value: 'luohe', label: '漯河市' },
      { value: 'sanmenxia', label: '三门峡市' },
      { value: 'nanyang', label: '南阳市' },
      { value: 'shangqiu', label: '商丘市' },
      { value: 'xinyang', label: '信阳市' },
      { value: 'zhoukou', label: '周口市' },
      { value: 'zhumadian', label: '驻马店市' }
    ]
  },
  {
    value: 'hubei',
    label: '湖北省',
    children: [
      { value: 'wuhan', label: '武汉市' },
      { value: 'huangshi', label: '黄石市' },
      { value: 'shiyan', label: '十堰市' },
      { value: 'yichang', label: '宜昌市' },
      { value: 'xiangyang', label: '襄阳市' },
      { value: 'ezhou', label: '鄂州市' },
      { value: 'jingmen', label: '荆门市' },
      { value: 'xiaogan', label: '孝感市' },
      { value: 'jingzhou', label: '荆州市' },
      { value: 'huanggang', label: '黄冈市' },
      { value: 'xianning', label: '咸宁市' },
      { value: 'suizhou', label: '随州市' }
    ]
  },
  {
    value: 'hunan',
    label: '湖南省',
    children: [
      { value: 'changsha', label: '长沙市' },
      { value: 'zhuzhou', label: '株洲市' },
      { value: 'xiangtan', label: '湘潭市' },
      { value: 'hengyang', label: '衡阳市' },
      { value: 'shaoyang', label: '邵阳市' },
      { value: 'yueyang', label: '岳阳市' },
      { value: 'changde', label: '常德市' },
      { value: 'zhangjiajie', label: '张家界市' },
      { value: 'yiyang', label: '益阳市' },
      { value: 'chenzhou', label: '郴州市' },
      { value: 'yongzhou', label: '永州市' },
      { value: 'huaihua', label: '怀化市' },
      { value: 'loudi', label: '娄底市' }
    ]
  }
];

// 预设选项类型
export const presetOptions = {
  chinaRegions: {
    name: '中国省市',
    data: chinaRegions
  }
};
