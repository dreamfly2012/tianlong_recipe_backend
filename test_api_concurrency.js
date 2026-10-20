/**
 * API并发测试脚本
 * 测试API的并发响应能力并统计执行时间
 */

// 测试配置
const API_URL = "http://127.0.0.1:5000/translate";
const CONCURRENT_REQUESTS = 10; // 并发请求数量
const TOTAL_REQUESTS = 100; // 总请求数量

// 随机测试句子数组，包含长短不一的句子
const testSentences = [
    "Hello world",
    "The quick brown fox jumps over the lazy dog. This sentence contains all letters of the English alphabet and is often used for typing practice.",
    "In a hole in the ground there lived a hobbit. Not a nasty, dirty, wet hole filled with the ends of worms and an oozy smell, nor yet a dry, bare, sandy hole with nothing in it to sit down on or to eat: it was a hobbit-hole, and that means comfort.",
    "It was the best of times, it was the worst of times, it was the age of wisdom, it was the age of foolishness, it was the epoch of belief, it was the epoch of incredulity, it was the season of Light, it was the season of Darkness, it was the spring of hope, it was the winter of despair.",
    "To be, or not to be, that is the question: Whether 'tis nobler in the mind to suffer the slings and arrows of outrageous fortune, or to take arms against a sea of troubles and by opposing end them.",
    "All happy families are alike; each unhappy family is unhappy in its own way.",
    "It is a truth universally acknowledged, that a single man in possession of a good fortune, must be in want of a wife.",
    "I am an invisible man. No, I am not a spook like those who haunted Edgar Allan Poe; nor am I one of your Hollywood movie extras.",
    "The sky above the port was the color of television, tuned to a dead channel. A breeze blew through the alley, rustling the pages of a newspaper.",
    "He was an old man who fished alone in a skiff in the Gulf Stream and he had gone eighty-four days now without taking a fish.",
    "Many years later, as he faced the firing squad, Colonel Aureliano Buendía was to remember that distant afternoon when his father took him to discover ice.",
    "We were somewhere around Barstow on the edge of the desert when the drugs began to take hold.",
    "The ships hung in the sky in much the same way that bricks don't.",
    "Once upon a time there was a little prince who lived on a very small planet, hardly bigger than a house.",
    "Someone must have been telling lies about Joseph K., he knew he had done nothing wrong but, one morning, he was arrested.",
    "The past is a foreign country; they do things differently there.",
    "Happy families are all alike; every unhappy family is unhappy in its own way.",
    "You don't know about me without you have read a book by the name of The Adventures of Tom Sawyer; but that ain't no matter.",
    "I have a dream that one day this nation will rise up and live out the true meaning of its creed.",
    "It was a bright cold day in April, and the clocks were striking thirteen.",
    "Time is not a line but a dimension, like the dimensions of space.",
    "The restaurant booths were arranged in a way that maximized customer privacy while maintaining an intimate atmosphere.",
    "Programming is the process of creating a set of instructions that tell a computer how to perform a task.",
    "Laravel is a web application framework with expressive, elegant syntax designed to make developing web applications easier and more enjoyable.",
    "Concurrent processing allows multiple operations to be executed simultaneously, improving overall system performance and responsiveness.",
    "Database migrations are like version control for your database, allowing your team to define and share the application's database schema definition.",
    "API rate limiting is a crucial security measure that prevents abuse by limiting the number of requests a client can make within a specific timeframe.",
    "Caching strategies can dramatically improve application performance by storing frequently accessed data in memory for faster retrieval.",
    "Object-oriented programming is a programming paradigm based on the concept of objects, which can contain data and code.",
    "Containerization with Docker provides a consistent environment for applications across different deployment stages, from development to production."
];

// 基础测试数据
const baseTestData = {
    source: "en",
    target: "ja",
    format: "text",
    alternatives: 0,
    api_key: ""
};

/**
 * 获取随机测试句子
 */
function getRandomSentence() {
    const randomIndex = Math.floor(Math.random() * testSentences.length);
    return testSentences[randomIndex];
}

/**
 * 发送单个请求并测量响应时间
 */
async function makeRequest(requestId) {
    // 为每个请求生成随机测试数据
    const testData = {
        ...baseTestData,
        q: getRandomSentence()
    };
    
    const startTime = process.hrtime.bigint();
    
    try {
        const res = await fetch(API_URL, {
            method: "POST",
            body: JSON.stringify(testData),
            headers: { "Content-Type": "application/json" }
        });
        
        const endTime = process.hrtime.bigint();
        const duration = Number(endTime - startTime) / 1000000; // 转换为毫秒
        
        const result = await res.json();
        return {
            requestId,
            success: true,
            duration,
            status: res.status,
            result,
            sentenceLength: testData.q.length,
            originalText: testData.q
        };
    } catch (error) {
        const endTime = process.hrtime.bigint();
        const duration = Number(endTime - startTime) / 1000000; // 转换为毫秒
        
        return {
            requestId,
            success: false,
            duration,
            error: error.message,
            sentenceLength: testData.q.length,
            originalText: testData.q
        };
    }
}

/**
 * 并发执行请求
 */
async function runConcurrentRequests(batchSize) {
    const promises = [];
    for (let i = 0; i < batchSize; i++) {
        promises.push(makeRequest(i + 1));
    }
    return Promise.all(promises);
}

/**
 * 运行完整的并发测试
 */
async function runConcurrencyTest() {
    console.log(`开始API并发测试...`);
    console.log(`并发请求数: ${CONCURRENT_REQUESTS}`);
    console.log(`总请求数: ${TOTAL_REQUESTS}`);
    console.log(`请求URL: ${API_URL}`);
    console.log('========================');
    
    const allResults = [];
    const startTime = Date.now();
    
    // 分批执行并发请求
    const batches = Math.ceil(TOTAL_REQUESTS / CONCURRENT_REQUESTS);
    for (let batch = 0; batch < batches; batch++) {
        const batchSize = Math.min(CONCURRENT_REQUESTS, TOTAL_REQUESTS - batch * CONCURRENT_REQUESTS);
        console.log(`执行第 ${batch + 1}/${batches} 批并发请求，数量: ${batchSize}`);
        
        const batchResults = await runConcurrentRequests(batchSize);
        allResults.push(...batchResults);
        
        // 批次间短暂间隔
        if (batch < batches - 1) {
            await new Promise(resolve => setTimeout(resolve, 100));
        }
    }
    
    const endTime = Date.now();
    const totalTime = endTime - startTime;
    
    // 统计结果
    const successfulRequests = allResults.filter(r => r.success).length;
    const failedRequests = allResults.length - successfulRequests;
    const totalDuration = allResults.reduce((sum, r) => sum + r.duration, 0);
    const averageDuration = totalDuration / allResults.length;
    
    // 计算最小/最大响应时间
    const durations = allResults.map(r => r.duration);
    const minDuration = Math.min(...durations);
    const maxDuration = Math.max(...durations);
    
    // 计算句子长度统计
    const sentenceLengths = allResults.map(r => r.sentenceLength);
    const avgSentenceLength = sentenceLengths.reduce((sum, len) => sum + len, 0) / sentenceLengths.length;
    const minSentenceLength = Math.min(...sentenceLengths);
    const maxSentenceLength = Math.max(...sentenceLengths);
    
    // 输出结果
    console.log('\n========================');
    console.log('测试完成! 结果统计:');
    console.log('========================');
    console.log(`总耗时: ${totalTime} ms`);
    console.log(`总请求数: ${allResults.length}`);
    console.log(`成功请求数: ${successfulRequests}`);
    console.log(`失败请求数: ${failedRequests}`);
    console.log(`成功率: ${(successfulRequests / allResults.length * 100).toFixed(2)}%`);
    console.log(`平均响应时间: ${averageDuration.toFixed(2)} ms`);
    console.log(`最短响应时间: ${minDuration.toFixed(2)} ms`);
    console.log(`最长响应时间: ${maxDuration.toFixed(2)} ms`);
    console.log(`QPS (每秒查询率): ${(allResults.length / (totalTime / 1000)).toFixed(2)}`);
    console.log(`平均句子长度: ${avgSentenceLength.toFixed(2)} 字符`);
    console.log(`最短句子长度: ${minSentenceLength} 字符`);
    console.log(`最长句子长度: ${maxSentenceLength} 字符`);
    
    // 显示详细结果
    console.log('\n详细结果:');
    console.log('------------------------');
    allResults.forEach((result, index) => {
        console.log(`\n[${index + 1}] 请求 #${result.requestId}:`);
        console.log(`    状态: ${result.success ? '成功' : '失败'}`);
        console.log(`    耗时: ${result.duration.toFixed(2)} ms`);
        console.log(`    句子长度: ${result.sentenceLength} 字符`);
        console.log(`    原文: "${result.originalText}"`);
        
        if (result.success) {
            console.log(`    HTTP状态码: ${result.status}`);
            console.log(`    翻译结果: "${result.result.translatedText || 'N/A'}"`);
            // 如果有其他字段也一并打印
            for (const key in result.result) {
                if (key !== 'translatedText') {
                    console.log(`    ${key}:`, typeof result.result[key] === 'object' 
                        ? JSON.stringify(result.result[key]) 
                        : result.result[key]);
                }
            }
        } else {
            console.log(`    错误信息: ${result.error}`);
        }
    });
    
    // 统计翻译结果长度
    const translatedLengths = allResults
        .filter(r => r.success)
        .map(r => (r.result.translatedText || '').length);
    
    if (translatedLengths.length > 0) {
        const avgTranslatedLength = translatedLengths.reduce((sum, len) => sum + len, 0) / translatedLengths.length;
        const minTranslatedLength = Math.min(...translatedLengths);
        const maxTranslatedLength = Math.max(...translatedLengths);
        
        console.log('\n翻译结果统计:');
        console.log('------------------------');
        console.log(`平均翻译结果长度: ${avgTranslatedLength.toFixed(2)} 字符`);
        console.log(`最短翻译结果长度: ${minTranslatedLength} 字符`);
        console.log(`最长翻译结果长度: ${maxTranslatedLength} 字符`);
    }
}

// 运行测试
runConcurrencyTest().catch(console.error);